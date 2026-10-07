<?php

namespace App\Console\Commands;

use App\Services\MinistrySchoolSync;
use Illuminate\Console\Command;

class ProcessMinistrySchools extends Command
{
    protected $signature = 'schools:process-ministry {--watch : Mantener el ejecutor activo} {--steps=1 : Pasos por importación en una ejecución}';
    protected $description = 'Procesa las importaciones oficiales iniciadas desde el panel';

    public function handle(MinistrySchoolSync $sync): int
    {
        $lock = null;
        if ($this->option('watch')) {
            \Illuminate\Support\Facades\File::ensureDirectoryExists(storage_path('app/importaciones/ministerio'));
            $lock = fopen(storage_path('app/importaciones/ministerio/ejecutor.lock'), 'c');
            if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
                $this->warn('Ya existe un ejecutor activo.');
                return self::SUCCESS;
            }
        }
        do {
            if ($this->option('watch')) {
                \Illuminate\Support\Facades\File::replace(storage_path('app/importaciones/ministerio/ejecutor.json'), json_encode(['pid' => getmypid(), 'actualizado_en' => time()]));
            }
            foreach ($sync->history() as $item) {
                if ($item['estado'] !== 'procesando') continue;
                for ($i = 0; $i < max(1, (int) $this->option('steps')); $i++) {
                    try { $sync->tick($item['id']); }
                    catch (\Throwable $e) { $this->error($e->getMessage()); break; }
                    usleep(750000);
                }
            }
            if ($this->option('watch')) sleep(2);
        } while ($this->option('watch'));
        return self::SUCCESS;
    }
}
