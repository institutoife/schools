<?php

namespace App\Console\Commands;

use App\Services\SchoolExcelImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class ImportGeneratedSchoolJson extends Command
{
    protected $signature = 'schools:import-json {directorio : Directorio de los nueve JSON del Excel}';
    protected $description = 'Crea o actualiza por RUE desde los JSON generados desde Excel';

    public function handle(SchoolExcelImporter $importer): int
    {
        try {
            $records = [];
            foreach (SchoolExcelImporter::DEPARTMENTS as $department => $slug) {
                $path = rtrim($this->argument('directorio'), '/\\').'/colegios_'.$slug.'.json';
                if (!is_file($path)) throw new \RuntimeException("Falta $path");
                $rows = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($rows) || !array_is_list($rows)) throw new \RuntimeException("Lista inválida: $path");
                foreach ($rows as $row) {
                    $validated = Validator::make($row, [
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
                        'departamento_clasificacion' => ['required', \Illuminate\Validation\Rule::in([$department])],
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
                        'infraestructura' => 'required|array:servicios,ambientes',
                        'infraestructura.servicios' => 'array:agua,electricidad,banos,internet',
                        'infraestructura.servicios.*' => 'boolean',
                        'infraestructura.ambientes' => 'array:aulas,laboratorios,bibliotecas,computacion,canchas,gimnasios,coliseos,piscinas,secretaria,reuniones,talleres',
                        'infraestructura.ambientes.*' => 'integer|min:0',
                    ])->validate();
                    $rue = $validated['general']['codigo_rue'];
                    if (isset($records[$rue])) throw new \RuntimeException("RUE repetido: $rue");
                    $records[$rue] = $validated;
                }
            }
            if (!$records) throw new \RuntimeException('No hay colegios.');
            $this->info(json_encode($importer->import(array_values($records)), JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
