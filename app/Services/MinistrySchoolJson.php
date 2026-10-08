<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;
use ZipArchive;

class MinistrySchoolJson
{
    private const MAX_BYTES = 104857600;

    /** A consumer stores each validated department without retaining all nine in memory. */
    public function readFiles(array $files, ?callable $consume = null): array
    {
        $groups = [];
        $seen = [];
        $bytes = 0;
        foreach ($files as $file) {
            if (!is_file($file['path'])) throw new RuntimeException('No se encuentra el archivo cargado.');
            if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) === 'zip') {
                $zip = new ZipArchive;
                if ($zip->open($file['path']) !== true) throw new RuntimeException('No se pudo abrir el ZIP.');
                try {
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $entry = $zip->statIndex($i);
                        $name = $entry['name'];
                        if (str_ends_with($name, '/')) continue;
                        // Read entries in memory. Never extract uploaded paths onto the server.
                        if (str_contains($name, '..') || str_contains($name, '\\') || str_starts_with($name, '/')) {
                            throw new RuntimeException('El ZIP contiene una ruta inválida.');
                        }
                        if (basename($name) === 'reporte-importacion.json') continue;
                        $this->checkSize($bytes, $entry['size']);
                        $content = $zip->getFromIndex($i);
                        if ($content === false) throw new RuntimeException("No se pudo leer $name del ZIP.");
                        $this->add($groups, $seen, basename($name), $content, $consume);
                    }
                } finally { $zip->close(); }
            } else {
                $this->checkSize($bytes, filesize($file['path']));
                $this->add($groups, $seen, $file['name'], file_get_contents($file['path']), $consume);
            }
        }
        if (!$seen) throw new RuntimeException('Los archivos no contienen colegios para importar.');
        return $groups;
    }

    private function checkSize(int &$total, int $size): void
    {
        $total += $size;
        if ($total > self::MAX_BYTES) throw new RuntimeException('El contenido JSON supera 100 MB. Carga menos departamentos a la vez.');
    }

    private function add(array &$groups, array &$seen, string $name, string $content, ?callable $consume): void
    {
        $slug = null;
        foreach (SchoolExcelImporter::DEPARTMENTS as $candidate) {
            if ($name === 'colegios_'.$candidate.'.json') $slug = $candidate;
        }
        if ($slug === null) throw new RuntimeException("Archivo no reconocido: $name. Usa los JSON generados por esta sección, sin renombrarlos.");
        if (array_key_exists($slug, $groups)) throw new RuntimeException("Departamento repetido: $name.");
        $department = array_search($slug, SchoolExcelImporter::DEPARTMENTS, true);
        try { $rows = json_decode($content, true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new RuntimeException("JSON inválido en $name: ".$e->getMessage()); }
        if (!is_array($rows) || !array_is_list($rows)) throw new RuntimeException("Se esperaba una lista de colegios en $name.");
        $groups[$slug] = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) throw new RuntimeException("Registro inválido en $name, fila ".($index + 1).'.');
            try { $validated = $this->validate($row, $department); }
            catch (\Illuminate\Validation\ValidationException $e) {
                throw new RuntimeException("$name, fila ".($index + 1).': '.implode(' ', $e->validator->errors()->all()));
            }
            $rue = $validated['general']['codigo_rue'];
            if (isset($seen[$rue])) throw new RuntimeException("RUE repetido entre los archivos: $rue.");
            $seen[$rue] = true;
            $groups[$slug][] = $validated;
        }
        if ($consume !== null) {
            $consume($slug, $groups[$slug]);
            $groups[$slug] = [];
        }
    }

    public function validate(array $row, string $department): array
    {
        $record = Validator::make($row, [
            'general' => 'required|array:codigo_rue,nombre,director,direccion,telefonos,dependencia,niveles,turnos,humanistico',
            'general.codigo_rue' => ['required', 'string', 'regex:/^\d{8}$/D'],
            'general.nombre' => 'required|string|max:100',
            'general.director' => 'sometimes|string|max:100',
            'general.direccion' => 'sometimes|string|max:150',
            'general.telefonos' => 'sometimes|string|max:35',
            'general.dependencia' => 'required|in:FISCAL,PRIVADO,CONVENIO',
            'general.niveles' => 'sometimes|string|max:70',
            'general.turnos' => 'sometimes|string|max:50',
            'general.humanistico' => 'sometimes|string|max:10',
            'fuente' => 'required|array',
            'fuente.gestion' => 'required|in:2025',
            'departamento_clasificacion' => ['required', Rule::in([$department])],
            'url' => 'sometimes|url|max:100',
            'ubicacion' => 'required|array:departamento,provincia,municipio,distrito,area,coordenadas',
            'ubicacion.departamento' => 'required|string|max:255',
            'ubicacion.area' => 'sometimes|in:URBANA,RURAL',
            'ubicacion.provincia' => 'sometimes|string|max:255',
            'ubicacion.municipio' => 'sometimes|string|max:255',
            'ubicacion.distrito' => 'sometimes|string|max:255',
            'ubicacion.coordenadas' => 'sometimes|array:latitud,longitud,texto',
            'ubicacion.coordenadas.texto' => 'sometimes|string|max:255',
            'ubicacion.coordenadas.latitud' => 'sometimes|numeric|between:-90,90',
            'ubicacion.coordenadas.longitud' => 'sometimes|numeric|between:-180,180',
            'estadisticas' => 'present|array:matricula,promovidos,reprobados,abandono',
            'estadisticas.*' => 'array:Total,Mujer,Hombre',
            'estadisticas.*.*' => 'array',
            'estadisticas.*.*.*' => 'integer|min:0',
            'infraestructura' => 'present|array:servicios,ambientes',
            'infraestructura.servicios' => 'sometimes|array:agua,electricidad,banos,internet',
            'infraestructura.servicios.*' => 'boolean',
            'infraestructura.ambientes' => 'sometimes|array:aulas,laboratorios,bibliotecas,computacion,canchas,gimnasios,coliseos,piscinas,secretaria,reuniones,talleres',
            'infraestructura.ambientes.*' => 'integer|min:0',
        ])->validate();
        foreach ($record['estadisticas'] as $series) {
            foreach ($series as $values) {
                foreach (array_keys($values) as $year) {
                    if (!preg_match('/^20\d{2}$/D', (string) $year) || (int) $year > 2025) {
                        throw new RuntimeException('Las estadísticas deben tener años válidos hasta la gestión 2025.');
                    }
                }
            }
        }
        return $record;
    }
}
