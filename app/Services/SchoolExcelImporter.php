<?php

namespace App\Services;

use App\Models\School;
use App\Models\Ubicacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class SchoolExcelImporter
{
    public const DEPARTMENTS = ['BENI' => 'beni', 'CHUQUISACA' => 'chuquisaca', 'COCHABAMBA' => 'cochabamba', 'LA PAZ' => 'lapaz', 'ORURO' => 'oruro', 'PANDO' => 'pando', 'POTOSÍ' => 'potosi', 'SANTA CRUZ' => 'santacruz', 'TARIJA' => 'tarija'];

    public function read(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);
        $records = [];
        try {
            foreach ($book->getAllSheets() as $sheet) {
                $header = false;
                foreach ($sheet->getRowIterator() as $row) {
                    $values = [];
                    foreach ($row->getCellIterator('A', 'C') as $cell) {
                        if ($cell->getColumn() === 'B') { $values[] = ''; continue; }
                        if ($cell->isFormula()) {
                            throw new RuntimeException('El Excel debe contener valores, no fórmulas.');
                        }
                        $values[] = trim((string) $cell->getValue());
                    }
                    if (mb_strtolower($values[0]) === 'código rue' || mb_strtolower($values[0]) === 'codigo rue') {
                        $header = true;
                        continue;
                    }
                    if (!$header || implode('', $values) === '') {
                        continue;
                    }
                    $context = $sheet->getTitle().':'.$row->getRowIndex();
                    $department = mb_strtoupper(preg_replace('/\s+/u', ' ', $values[2]));
                    if ($department === 'POTOSI') $department = 'POTOSÍ';
                    if (!preg_match('/^\d{8}$/D', $values[0]) || !isset(self::DEPARTMENTS[$department])) {
                        throw new RuntimeException("RUE o departamento inv?lido en $context.");
                    }
                    $record = ['general' => ['codigo_rue' => $values[0]], 'ubicacion' => ['departamento' => $department]];
                    $key = $values[0];
                    if (isset($records[$key]) && $records[$key] !== $record) {
                        throw new RuntimeException("RUE duplicado con datos distintos en $context: $key.");
                    }
                    $records[$key] = $record;
                }
            }
        } finally {
            $book->disconnectWorksheets();
        }
        if (!$records) throw new RuntimeException('No se encontraron colegios con encabezado Código RUE.');
        ksort($records);
        return array_values($records);
    }

    public function export(array $records, string $directory): array
    {
        File::ensureDirectoryExists($directory);
        $groups = array_fill_keys(array_keys(self::DEPARTMENTS), []);
        foreach ($records as $record) $groups[$record['ubicacion']['departamento']][] = $record;
        $counts = [];
        foreach (self::DEPARTMENTS as $department => $slug) {
            File::put($directory.'/colegios_'.$slug.'.json', json_encode($groups[$department], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $counts[$department] = count($groups[$department]);
        }
        return $counts;
    }

    public function import(array $records): array
    {
        return DB::transaction(function () use ($records) {
            $counts = ['nuevos' => 0, 'actualizados' => 0, 'sin_cambios' => 0];
            foreach ($records as $record) {
                $school = School::firstOrNew(['codigo_rue' => $record['general']['codigo_rue']]);
                $new = !$school->exists;
                $school->fill($record['general']);
                if (isset($record['url'])) $school->url_ficha = $record['url'];
                $changed = $school->isDirty();
                if ($new && empty($record['general']['nombre'])) throw new RuntimeException('No se puede crear un colegio sin ficha oficial.');
                if ($changed || $new) $school->save();
                $location = Ubicacion::firstOrNew(['school_id' => $school->id]);
                $data = $record['ubicacion'];
                $coords = $data['coordenadas'] ?? [];
                unset($data['coordenadas']);
                foreach ($coords as $field => $value) {
                    if ($field === 'texto') { $data['coordenadas_texto'] = $value; unset($coords[$field]); continue; }
                    $coords[$field] = number_format($value, 8, '.', '');
                    if ($location->exists && $location->$field !== null && number_format((float) $location->$field, 8, '.', '') === $coords[$field]) {
                        unset($coords[$field]);
                    }
                }
                $location->fill(array_merge($data, $coords));
                $changed = $changed || $location->isDirty();
                if ($location->isDirty() || !$location->exists) $location->save();
                foreach (['servicios' => \App\Models\Servicio::class, 'ambientes' => \App\Models\Ambiente::class] as $section => $model) {
                    $values = $record['infraestructura'][$section] ?? [];
                    if (!$values) continue;
                    $related = $model::firstOrNew(['school_id' => $school->id]);
                    $related->fill($values);
                    $changed = $changed || $related->isDirty();
                    if ($related->isDirty() || !$related->exists) $related->save();
                }
                foreach ($record['estadisticas'] ?? [] as $category => $statistics) {
                    $years = [];
                    foreach ($statistics as $values) $years = array_merge($years, array_keys($values));
                    foreach (array_unique($years) as $year) {
                        if ((int) $year > 2025) continue;
                        $stat = \App\Models\Estadistica::firstOrNew(['school_id' => $school->id, 'categoria' => $category, 'anio' => (int) $year]);
                        $values = [];
                        foreach (['Total' => 'total', 'Mujer' => 'mujer', 'Hombre' => 'hombre'] as $sex => $field) {
                            if (array_key_exists($year, $statistics[$sex] ?? [])) $values[$field] = $statistics[$sex][$year];
                        }
                        $stat->fill($values);
                        $changed = $changed || $stat->isDirty();
                        if ($stat->isDirty() || !$stat->exists) $stat->save();
                    }
                }
                $counts[$new ? 'nuevos' : ($changed ? 'actualizados' : 'sin_cambios')]++;
            }
            return $counts;
        });
    }
}
