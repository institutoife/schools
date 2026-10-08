<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class MinistrySchoolSync
{
    public function directory(string $id): string
    {
        if (!Str::isUuid($id)) throw new RuntimeException('Importación inválida.');
        return storage_path('app/importaciones/ministerio/'.$id);
    }

    public function state(string $id): array
    {
        $path = $this->directory($id).'/estado.json';
        if (!is_file($path)) throw new RuntimeException('No existe la importación.');
        return json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function save(string $id, array $state): void
    {
        File::replace($this->directory($id).'/estado.json', json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function create(string $excel): string
    {
        $identifiers = app(SchoolExcelImporter::class)->read($excel);
        $id = (string) Str::uuid();
        $directory = $this->directory($id);
        File::ensureDirectoryExists($directory.'/fichas');
        File::ensureDirectoryExists($directory.'/json');
        File::copy($excel, $directory.'/fuente.xlsx');
        $departments = [];
        foreach (SchoolExcelImporter::DEPARTMENTS as $name => $slug) {
            $departments[$slug] = ['nombre' => $name, 'estado' => 'pendiente', 'colegios' => [], 'resultado' => null, 'error' => null];
            File::put($directory.'/json/colegios_'.$slug.'.json', '[]');
        }
        foreach ($identifiers as $record) {
            $slug = SchoolExcelImporter::DEPARTMENTS[$record['ubicacion']['departamento']];
            $departments[$slug]['colegios'][$record['general']['codigo_rue']] = ['estado' => 'pendiente', 'intentos' => 0, 'error' => null];
        }
        $this->save($id, ['id' => $id, 'gestion' => 2025, 'creado_en' => now()->toIso8601String(), 'estado' => 'procesando', 'ultimo_rue' => null, 'departamentos' => $departments]);
        return $id;
    }

    public function createFromJson(array $files): string
    {
        $id = (string) Str::uuid();
        $directory = $this->directory($id);
        File::ensureDirectoryExists($directory.'/json');
        File::ensureDirectoryExists($directory.'/lotes');
        $departments = [];
        try {
            // Stage one department at a time; register work only after all files validate.
            app(MinistrySchoolJson::class)->readFiles($files, function (string $slug, array $records) use ($directory, &$departments) {
                $schools = [];
                foreach ($records as $record) {
                    $schools[$record['general']['codigo_rue']] = ['estado' => 'leido', 'intentos' => 0, 'error' => null];
                }
                $departments[$slug] = [
                    'nombre' => array_search($slug, SchoolExcelImporter::DEPARTMENTS, true), 'estado' => 'pendiente',
                    'colegios' => $schools, 'importados' => 0, 'resultado' => null, 'error' => null,
                ];
                File::put($directory.'/json/colegios_'.$slug.'.json', json_encode($records, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                foreach (array_chunk($records, 50) as $index => $batch) {
                    File::put($directory.'/lotes/'.$slug.'-'.$index.'.json', json_encode($batch, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                }
            });
        } catch (\Throwable $e) {
            File::deleteDirectory($directory);
            throw $e;
        }
        foreach (SchoolExcelImporter::DEPARTMENTS as $name => $slug) {
            if (isset($departments[$slug])) continue;
            $departments[$slug] = [
                'nombre' => $name, 'estado' => 'omitido',
                'colegios' => [], 'importados' => 0, 'resultado' => null, 'error' => null,
            ];
            File::put($directory.'/json/colegios_'.$slug.'.json', '[]');
        }
        $this->save($id, [
            'id' => $id, 'gestion' => 2025, 'origen' => 'json', 'creado_en' => now()->toIso8601String(),
            'estado' => 'procesando', 'ultimo_rue' => null, 'departamentos' => $departments,
        ]);
        return $id;
    }

    private function tickJson(string $id, array &$state): void
    {
        foreach ($state['departamentos'] as $slug => &$department) {
            if (in_array($department['estado'], ['completo', 'omitido', 'con_errores'], true)) continue;
            try {
                $directory = $this->directory($id);
                $batchPath = $directory.'/lotes/'.$slug.'-'.intdiv($department['importados'], 50).'.json';
                if (is_dir($directory.'/lotes')) {
                    $batch = count($department['colegios']) === 0 ? [] : json_decode(File::get($batchPath), true, 512, JSON_THROW_ON_ERROR);
                } else {
                    $rows = json_decode(File::get($directory.'/json/colegios_'.$slug.'.json'), true, 512, JSON_THROW_ON_ERROR);
                    $batch = array_slice($rows, $department['importados'], 50);
                }
                $counts = app(SchoolExcelImporter::class)->import($batch);
                $department['resultado'] ??= ['nuevos' => 0, 'actualizados' => 0, 'sin_cambios' => 0];
                foreach ($counts as $key => $value) $department['resultado'][$key] += $value;
                $department['importados'] += count($batch);
                $department['estado'] = $department['importados'] >= count($department['colegios']) ? 'completo' : 'procesando';
                $department['error'] = null;
                if ($batch) $state['ultimo_rue'] = end($batch)['general']['codigo_rue'];
            } catch (\Throwable $e) {
                $department['error'] = mb_substr($e->getMessage(), 0, 1000);
                $department['estado'] = 'con_errores';
            }
            return;
        }
        unset($department);
        $state['estado'] = count(array_filter($state['departamentos'], fn ($department) => $department['estado'] === 'con_errores')) ? 'con_errores' : 'completo';
    }

    public function change(string $id, string $action): void
    {
        $this->locked($id, function (array &$state) use ($action) {
            if ($action === 'pausar') $state['estado'] = 'pausado';
            if ($action === 'reanudar') $state['estado'] = 'procesando';
            if ($action === 'reintentar') {
                foreach ($state['departamentos'] as &$department) {
                    foreach ($department['colegios'] as &$school) {
                        if ($school['estado'] === 'error') $school = ['estado' => 'pendiente', 'intentos' => 0, 'error' => null];
                    }
                    unset($school);
                    if (!in_array($department['estado'], ['completo', 'omitido'], true)) $department['estado'] = 'pendiente';
                    $department['error'] = null;
                }
                unset($department);
                $state['estado'] = 'procesando';
            }
        }, true);
    }

    private function locked(string $id, callable $callback, bool $wait = false): void
    {
        $handle = fopen($this->directory($id).'/proceso.lock', 'c');
        if (!$handle) throw new RuntimeException('No se pudo bloquear la importación.');
        try {
            if (!flock($handle, LOCK_EX | ($wait ? 0 : LOCK_NB))) return;
            $state = $this->state($id);
            $callback($state);
            $this->save($id, $state);
        } finally { flock($handle, LOCK_UN); fclose($handle); }
    }

    public function tick(string $id): void
    {
        $this->locked($id, function (array &$state) use ($id) {
            if ($state['estado'] !== 'procesando') return;
            if (($state['origen'] ?? '') === 'json') {
                $this->tickJson($id, $state);
                return;
            }
            $directory = $this->directory($id);
            foreach ($state['departamentos'] as $slug => &$department) {
                if (in_array($department['estado'], ['completo', 'con_errores'], true)) continue;
                $department['estado'] = 'procesando';
                foreach ($department['colegios'] as $rue => &$school) {
                    if ($school['estado'] !== 'pendiente') continue;
                    if (isset($state['ultima_consulta']) && microtime(true) - $state['ultima_consulta'] < 1) return;
                    $state['ultima_consulta'] = microtime(true);
                    $state['ultimo_rue'] = (string) $rue;
                    $school['intentos']++;
                    try {
                        $record = app(MinistrySchoolClient::class)->fetch((string) $rue);
                        $record['fuente']['departamento_excel'] = $department['nombre'];
                        $record['departamento_clasificacion'] = $department['nombre'];
                        File::replace($directory.'/fichas/'.$rue.'.json', json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                        $school['estado'] = 'leido';
                        $school['error'] = null;
                    } catch (\Throwable $e) {
                        $school['error'] = mb_substr($e->getMessage(), 0, 1000);
                        if ($school['intentos'] >= 3) $school['estado'] = 'error';
                    }
                    return;
                }
                unset($school);
                $records = [];
                foreach ($department['colegios'] as $rue => $school) {
                    if ($school['estado'] === 'leido') $records[] = json_decode(File::get($directory.'/fichas/'.$rue.'.json'), true, 512, JSON_THROW_ON_ERROR);
                }
                $path = $directory.'/json/colegios_'.$slug.'.json';
                File::replace($path, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                try {
                    // Import exactly the JSON just generated, once the department has finished reading.
                    $department['resultado'] = app(SchoolExcelImporter::class)->import(json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR));
                    $errors = count(array_filter($department['colegios'], fn ($school) => $school['estado'] === 'error'));
                    $department['estado'] = $errors ? 'con_errores' : 'completo';
                    $department['error'] = null;
                } catch (\Throwable $e) {
                    $department['error'] = mb_substr($e->getMessage(), 0, 1000);
                    $department['estado'] = 'con_errores';
                }
                return;
            }
            unset($department);
            $state['estado'] = count(array_filter($state['departamentos'], fn ($department) => $department['estado'] === 'con_errores')) ? 'con_errores' : 'completo';
        });
    }

    public function history(): array
    {
        $root = storage_path('app/importaciones/ministerio');
        if (!is_dir($root)) return [];
        $result = [];
        foreach (File::directories($root) as $directory) {
            if (!is_file($directory.'/estado.json')) continue;
            $state = $this->state(basename($directory));
            $result[] = ['id' => $state['id'], 'fecha' => $state['creado_en'], 'estado' => $state['estado'], 'origen' => $state['origen'] ?? 'excel'];
        }
        usort($result, fn ($a, $b) => strcmp($b['fecha'], $a['fecha']));
        return $result;
    }

    public function workerRunning(): bool
    {
        $path = storage_path('app/importaciones/ministerio/ejecutor.json');
        if (!is_file($path)) return false;
        $worker = json_decode(File::get($path), true);
        return ($worker['actualizado_en'] ?? 0) > time() - 180;
    }

    public function archive(string $id): string
    {
        $state = $this->state($id);
        if (!in_array($state['estado'], ['completo', 'con_errores'], true)) throw new RuntimeException('Espera a que termine el procesamiento.');
        $directory = $this->directory($id);
        $path = $directory.'/colegios-ministerio-2025.zip';
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) throw new RuntimeException('No se pudo crear el ZIP.');
        foreach (SchoolExcelImporter::DEPARTMENTS as $slug) $zip->addFile($directory.'/json/colegios_'.$slug.'.json', 'colegios_'.$slug.'.json');
        $zip->addFile($directory.'/estado.json', 'reporte-importacion.json');
        $zip->close();
        return $path;
    }
}
