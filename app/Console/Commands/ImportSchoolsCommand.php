<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use App\Models\School;
use App\Models\Ubicacion;
use App\Models\Servicio;
use App\Models\Ambiente;
use App\Models\Estadistica;
use Throwable;

class ImportSchoolsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:schools';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import schools from varios archivos JSON en la base de datos';

    /**
     * Execute the console command.
     */
    public function handle()
    {

    $this->info('Iniciando la importación de colegios...');

    // Lista de archivos JSON a importar
    $archivos = [
        'colegios_beni.json',
        'colegios_chuquisaca.json',
        'colegios_cochabamba.json',
        'colegios_lapaz.json',
        'colegios_oruro.json',
        'colegios_pando.json',
        'colegios_potosi.json',
        'colegios_santacruz.json',
        'colegios_tarija.json',
    ];

    DB::beginTransaction();
    try {
        foreach ($archivos as $archivo) {
            $jsonPath = base_path($archivo);
            if (!File::exists($jsonPath)) {
                $this->warn("El archivo $archivo no se encuentra en la raíz del proyecto, omitiendo...");
                continue;
            }

            $jsonData = File::get($jsonPath);
            $schoolsData = json_decode($jsonData, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Error al decodificar el archivo ' . $archivo . ': ' . json_last_error_msg());
                continue;
            }

            $this->info("Importando colegios desde $archivo...");
            $this->withProgressBar($schoolsData, function ($schoolData) {
                // Verificar si el código_rue ya existe
                $codigoRue = $schoolData['general']['codigo_rue'] ?? null;
                if (!$codigoRue) {
                    $this->warn('Registro sin código RUE, omitiendo...');
                    return;
                }
                $existingSchool = School::where('codigo_rue', $codigoRue)->first();
                if ($existingSchool) {
                    $this->newLine();
                    $this->line("Colegio con código RUE {$codigoRue} ya existe, omitiendo...");
                    return;
                }
                // Crear la escuela (School) - Solo si no existe
                $school = School::create([
                    'nombre' => $schoolData['general']['nombre'] ?? 'N/A',
                    'codigo_rue' => $codigoRue,
                    'director' => $schoolData['general']['director'] ?? 'N/A',
                    'direccion' => $schoolData['general']['direccion'] ?? 'N/A',
                    'telefonos' => $schoolData['general']['telefonos'] ?? 'N/A',
                    'dependencia' => $schoolData['general']['dependencia'] ?? 'N/A',
                    'niveles' => $schoolData['general']['niveles'] ?? 'N/A',
                    'turnos' => $schoolData['general']['turnos'] ?? 'N/A',
                    'humanistico' => $schoolData['general']['humanistico'] ?? 'N/A',
                    'url_ficha' => $schoolData['url'] ?? '#',
                ]);
                // Resto del código para ubicación, infraestructura y estadísticas...
                if (isset($schoolData['ubicacion'])) {
                    Ubicacion::create([
                        'school_id' => $school->id,
                        'departamento' => $schoolData['ubicacion']['departamento'] ?? null,
                        'provincia' => $schoolData['ubicacion']['provincia'] ?? null,
                        'municipio' => $schoolData['ubicacion']['municipio'] ?? null,
                        'distrito' => $schoolData['ubicacion']['distrito'] ?? null,
                        'area' => $schoolData['ubicacion']['area'] ?? null,
                        'latitud' => $schoolData['ubicacion']['coordenadas']['latitud'] ?? null,
                        'longitud' => $schoolData['ubicacion']['coordenadas']['longitud'] ?? null,
                        'coordenadas_texto' => $schoolData['ubicacion']['coordenadas']['texto'] ?? null,
                    ]);
                }
                if (isset($schoolData['infraestructura'])) {
                    if (isset($schoolData['infraestructura']['servicios'])) {
                        Servicio::create([
                            'school_id' => $school->id,
                            'agua' => $schoolData['infraestructura']['servicios']['agua'] ?? null,
                            'electricidad' => $schoolData['infraestructura']['servicios']['electricidad'] ?? null,
                            'banos' => $schoolData['infraestructura']['servicios']['banos'] ?? null,
                            'internet' => $schoolData['infraestructura']['servicios']['internet'] ?? null,
                        ]);
                    }
                    if (isset($schoolData['infraestructura']['ambientes'])) {
                        Ambiente::create([
                            'school_id' => $school->id,
                            'aulas' => $schoolData['infraestructura']['ambientes']['aulas'] ?? null,
                            'laboratorios' => $schoolData['infraestructura']['ambientes']['laboratorios'] ?? null,
                            'bibliotecas' => $schoolData['infraestructura']['ambientes']['bibliotecas'] ?? null,
                            'computacion' => $schoolData['infraestructura']['ambientes']['computacion'] ?? null,
                            'canchas' => $schoolData['infraestructura']['ambientes']['canchas'] ?? null,
                            'gimnasios' => $schoolData['infraestructura']['ambientes']['gimnasios'] ?? null,
                            'coliseos' => $schoolData['infraestructura']['ambientes']['coliseos'] ?? null,
                            'piscinas' => $schoolData['infraestructura']['ambientes']['piscinas'] ?? null,
                            'secretaria' => $schoolData['infraestructura']['ambientes']['secretaria'] ?? null,
                            'reuniones' => $schoolData['infraestructura']['ambientes']['reuniones'] ?? null,
                            'talleres' => $schoolData['infraestructura']['ambientes']['talleres'] ?? null,
                        ]);
                    }
                }
                if (isset($schoolData['estadisticas'])) {
                    foreach ($schoolData['estadisticas'] as $category => $categoryData) {
                        if (isset($categoryData['Total']) && is_array($categoryData['Total'])) {
                            foreach ($categoryData['Total'] as $year => $totalValue) {
                                Estadistica::create([
                                    'school_id' => $school->id,
                                    'categoria' => $category,
                                    'anio' => (int) $year,
                                    'total' => $totalValue ?? 0,
                                    'mujer' => $categoryData['Mujer'][$year] ?? 0,
                                    'hombre' => $categoryData['Hombre'][$year] ?? 0,
                                ]);
                            }
                        }
                    }
                }
            });
        }
        DB::commit();
        $this->info("\n¡Importación completada exitosamente!");
    } catch (Throwable $e) {
        DB::rollBack();
        $this->error("\nOcurrió un error durante la importación: " . $e->getMessage());
        $this->error("Línea: " . $e->getLine() . " en " . $e->getFile());
        return 1;
    }
    return 0;
    }
}