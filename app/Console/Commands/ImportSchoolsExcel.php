<?php

namespace App\Console\Commands;

use App\Services\MinistrySchoolSync;
use Illuminate\Console\Command;

class ImportSchoolsExcel extends Command
{
    protected $signature = 'schools:import-excel {archivo : Excel con RUE y departamento}';
    protected $description = 'Registra una importaci?n desde las fichas oficiales del Ministerio, gesti?n 2025';

    public function handle(MinistrySchoolSync $sync): int
    {
        try {
            $id = $sync->create($this->argument('archivo'));
            $this->info('Importaci?n registrada: '.$id);
            $this->info('El Excel solo aporta RUE y departamento. Procesa desde el panel o con php artisan schools:process-ministry --watch');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
