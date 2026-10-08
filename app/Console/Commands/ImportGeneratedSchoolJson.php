<?php

namespace App\Console\Commands;

use App\Services\SchoolExcelImporter;
use Illuminate\Console\Command;
use App\Services\MinistrySchoolJson;

class ImportGeneratedSchoolJson extends Command
{
    protected $signature = 'schools:import-json {directorio : Directorio de los nueve JSON oficiales}';
    protected $description = 'Crea o actualiza por RUE desde JSON oficiales ya descargados, sin consultar al Ministerio';

    public function handle(SchoolExcelImporter $importer, MinistrySchoolJson $json): int
    {
        try {
            $files = [];
            foreach (SchoolExcelImporter::DEPARTMENTS as $slug) {
                $name = 'colegios_'.$slug.'.json';
                $files[] = ['path' => rtrim($this->argument('directorio'), '/\\').'/'.$name, 'name' => $name];
            }
            $groups = $json->readFiles($files);
            $records = array_merge(...array_values($groups));
            $this->info(json_encode($importer->import(array_values($records)), JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
