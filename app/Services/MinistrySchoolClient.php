<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MinistrySchoolClient
{
    public const BASE_URL = 'https://seie.minedu.gob.bo/reportes/mapas_unidades_educativas/ficha/ver/';

    public function fetch(string $rue): array
    {
        if (!preg_match('/^\d{8}$/D', $rue)) throw new RuntimeException('RUE inválido');
        $response = Http::connectTimeout(5)->timeout(15)->withHeaders(['User-Agent' => 'Educabol School Synchronization/1.0'])
            ->withOptions(['allow_redirects' => false])->get(self::BASE_URL.$rue);
        $response->throw();
        if ($response->status() !== 200) throw new RuntimeException('La ficha no devolvió HTTP 200.');
        return $this->parse($response->body(), $rue);
    }

    public function parse(string $html, string $rue): array
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $xpath = new DOMXPath($dom);
        $clean = fn ($text) => trim(preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $text)));
        $all = $clean($dom->textContent);
        if (!preg_match('/(?:datos correspondientes a la gesti[oó]n|informaci[oó]n administrativa, gesti[oó]n)\s+2025/iu', $all)) {
            throw new RuntimeException('La ficha no confirma datos de la gestión 2025.');
        }
        $general = [];
        foreach ($xpath->query('//strong') as $node) {
            $text = $clean($node->textContent);
            if (preg_match('/^UNIDAD EDUCATIVA:\s*(.+)$/u', $text, $m)) $general['nombre'] = $m[1];
            if (preg_match('/^C[ÓO]DIGO RUE:\s*(\d+)$/u', $text, $m)) $general['codigo_rue'] = $m[1];
        }
        if (($general['codigo_rue'] ?? '') !== $rue || empty($general['nombre'])) throw new RuntimeException('RUE o nombre de ficha no válido.');
        $labels = [];
        foreach ($xpath->query('//dt') as $node) {
            $next = $xpath->query('following-sibling::dd[1]', $node)->item(0);
            if ($next) $labels[mb_strtolower($clean($node->textContent))] = $clean($next->textContent);
        }
        $missing = fn ($v) => in_array($v, ['', '--', 'N/A'], true);
        foreach (['director(a):' => 'director', 'dirección:' => 'direccion', 'teléfono(s):' => 'telefonos', 'dependencia:' => 'dependencia', 'nivel(es):' => 'niveles', 'turno(s):' => 'turnos'] as $label => $field) {
            if (isset($labels[$label]) && !$missing($labels[$label])) $general[$field] = $labels[$label];
        }
        if (!isset($general['dependencia']) || !in_array($general['dependencia'], ['FISCAL', 'PRIVADO', 'CONVENIO'], true)) throw new RuntimeException('Dependencia oficial desconocida o ausente.');
        $location = [];
        foreach (['departamento:' => 'departamento', 'provincia:' => 'provincia', 'municipio:' => 'municipio', 'distrito educativo:' => 'distrito', 'área geográfica:' => 'area'] as $label => $field) {
            if (isset($labels[$label]) && !$missing($labels[$label])) $location[$field] = $labels[$label];
        }
        foreach ($labels as $label => $value) {
            if (str_contains($label, 'coordenadas') && preg_match('/Y:\s*(-?\d+(?:\.\d+)?)\s*X:\s*(-?\d+(?:\.\d+)?)/i', $value, $m)) {
                if (abs((float) $m[1]) > 90 || abs((float) $m[2]) > 180) throw new RuntimeException('Coordenadas oficiales fuera de rango.');
                $location['coordenadas'] = ['latitud' => (float) $m[1], 'longitud' => (float) $m[2], 'texto' => $value];
            }
        }
        if (empty($location['departamento'])) throw new RuntimeException('La ficha no publica departamento.');
        if (isset($location['area']) && !in_array($location['area'], ['URBANA', 'RURAL'], true)) throw new RuntimeException('Área oficial desconocida.');
        $statistics = [];
        foreach (['Matrícula escolar' => 'matricula', 'Estudiantes promovidos' => 'promovidos', 'Estudiantes reprobados' => 'reprobados', 'Estudiantes retirados por abandono' => 'abandono'] as $title => $category) {
            foreach ($xpath->query('//h3') as $heading) {
                if ($clean($heading->textContent) !== $title) continue;
                $box = $xpath->query('ancestor::div[contains(concat(" ", normalize-space(@class), " "), " box ")][1]', $heading)->item(0);
                $table = $box ? $xpath->query('.//table[1]', $box)->item(0) : null;
                if (!$table) continue;
                $years = [];
                foreach ($xpath->query('.//thead//th', $table) as $index => $th) {
                    $year = $clean($th->textContent);
                    if (preg_match('/^20\d{2}$/D', $year) && (int) $year <= 2025) $years[$index] = $year;
                }
                foreach ($xpath->query('.//tbody/tr', $table) as $tr) {
                    $cells = $xpath->query('./td', $tr);
                    $sex = $cells->length ? $clean($cells->item(0)->textContent) : '';
                    if (!in_array($sex, ['Total', 'Mujer', 'Hombre'], true)) continue;
                    foreach ($years as $index => $year) {
                        if (!$cells->item($index)) continue;
                        $value = $clean($cells->item($index)->textContent);
                        if ($missing($value)) continue;
                        $number = str_replace(['.', ',', ' '], '', $value);
                        if (!ctype_digit($number)) throw new RuntimeException("Estadística inválida: $category/$sex/$year");
                        $statistics[$category][$sex][$year] = (int) $number;
                    }
                }
            }
        }
        $infrastructure = ['servicios' => [], 'ambientes' => []];
        foreach ($xpath->query('//span[contains(@class,"info-box-number")]') as $node) {
            $map = ['Servicio de agua' => 'agua', 'Servicio de energía eléctrica' => 'electricidad', 'Baterías de baño' => 'banos', 'Internet' => 'internet'];
            $field = $map[$clean($node->textContent)] ?? null;
            $valueNode = $xpath->query('following::strong[1]', $node)->item(0);
            if (!$field || !$valueNode) continue;
            $value = mb_strtoupper($clean($valueNode->textContent));
            if ($missing($value)) continue;
            if (in_array($value, ['SI', 'SÍ', 'NO', '1', '0'], true)) $infrastructure['servicios'][$field] = in_array($value, ['SI', 'SÍ', '1'], true);
            else throw new RuntimeException("Servicio oficial no reconocido: $field=$value");
        }
        $ambientLabels = ['Nº de Aulas:' => 'aulas', 'Nº de Laboratorios:' => 'laboratorios', 'Nº de Bibliotecas:' => 'bibliotecas', 'Nº de Salas de Computación:' => 'computacion', 'Nº de Canchas:' => 'canchas', 'Nº de Gimnasios:' => 'gimnasios', 'Nº de Coliseos:' => 'coliseos', 'Nº de Piscinas:' => 'piscinas', 'Secretaría:' => 'secretaria', 'Sala de reuniones:' => 'reuniones', 'Nº de Talleres:' => 'talleres'];
        foreach ($xpath->query('//li/b') as $node) {
            $label = $clean($node->textContent);
            $field = $ambientLabels[$label] ?? null;
            $value = trim(mb_substr($clean($node->parentNode->textContent), mb_strlen($label)));
            if (!$field || $missing($value)) continue;
            if (ctype_digit($value)) $infrastructure['ambientes'][$field] = (int) $value;
            elseif (in_array(mb_strtoupper($value), ['SI', 'SÍ', 'NO'], true)) $infrastructure['ambientes'][$field] = mb_strtoupper($value) === 'NO' ? 0 : 1;
            else throw new RuntimeException("Ambiente oficial no reconocido: $field=$value");
        }
        foreach ($xpath->query('//h3') as $node) {
            if (preg_match('/Ofrece bachillerato técnico humanístico:\s*(.+)/iu', $clean($node->textContent), $m) && !$missing($m[1])) $general['humanistico'] = $m[1];
        }
        foreach (['nombre' => 100, 'director' => 100, 'direccion' => 150, 'telefonos' => 35, 'niveles' => 70, 'turnos' => 50, 'humanistico' => 10] as $field => $limit) {
            if (isset($general[$field]) && mb_strlen($general[$field]) > $limit) throw new RuntimeException("El campo oficial $field excede el tamaño de la base.");
        }
        return ['general' => $general, 'ubicacion' => $location, 'estadisticas' => $statistics, 'infraestructura' => $infrastructure,
            'url' => self::BASE_URL.$rue, 'fuente' => ['gestion' => 2025, 'consultado_en' => now()->toIso8601String(), 'anios_disponibles' => array_values(array_unique(array_merge(...array_values(array_map(fn ($s) => array_keys($s['Total'] ?? []), $statistics)))))]];
    }
}
