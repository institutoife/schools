<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Http\Requests\StoreSchoolRequest;
use App\Http\Requests\UpdateSchoolRequest;
use App\Models\Estadistica;
use App\Models\Ubicacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\RankingsController;

class SchoolController extends Controller
{
    // app/Http/Controllers/SchoolController.php
public function index(Request $request)
{
        $search = trim((string) $request->input('search'));
        $filter = $request->input('filter', 'nombre'); // Valor por defecto
        
        $query = School::query()
            ->with(['ubicacion'])
            ->select('id', 'nombre', 'codigo_rue', 'dependencia');
        
        if ($search) {
            $query->where(function($q) use ($search, $filter) {
                if ($filter === 'codigo') {
                    $q->where('codigo_rue', 'like', "%$search%");
                } elseif ($filter === 'zona') {
                    $q->where('direccion', 'like', "%$search%");
                } elseif (in_array($filter, ['departamento', 'municipio', 'distrito'], true)) {
                    $q->whereHas('ubicacion', function($q) use ($search) {
                        $filter = request('filter', 'departamento');
                        $q->where($filter, 'like', "%$search%");
                    });
                } else {
                    $q->where('nombre', 'like', "%$search%")
                        ->orWhere('codigo_rue', 'like', "%$search%")
                        ->orWhereHas('ubicacion', function ($location) use ($search) {
                            $location->where('departamento', 'like', "%$search%")
                                ->orWhere('municipio', 'like', "%$search%")
                                ->orWhere('distrito', 'like', "%$search%");
                        });
                }
            });
        }
        
        $schools = $query->paginate(10)->appends($request->query());

        // KPIs y destacados para la portada
        $kpis = RankingsController::buildKpis();
        $departments = Ubicacion::query()
            ->select('departamento', DB::raw('COUNT(DISTINCT school_id) as schools_count'))
            ->whereNotNull('departamento')
            ->where('departamento', '<>', '')
            ->groupBy('departamento')
            ->orderBy('departamento')
            ->get();
        $locationStats = [
            'departments' => $departments->count(),
            'municipalities' => Ubicacion::whereNotNull('municipio')->where('municipio', '<>', '')->distinct()->count('municipio'),
            'districts' => Ubicacion::whereNotNull('distrito')->where('distrito', '<>', '')->distinct()->count('distrito'),
        ];
        $featuredSchools = School::with('ubicacion')->orderBy('nombre')->limit(6)->get();

        return view('welcome', compact('schools', 'search', 'filter', 'kpis', 'departments', 'locationStats', 'featuredSchools'));
}

    public function welcomeSearch(Request $request)
    {
        $search = $request->input('search');
        $filter = $request->input('filter', 'nombre'); // Valor por defecto
        
        $query = School::query()
            ->with(['ubicacion'])
            ->select('id', 'nombre', 'codigo_rue', 'dependencia');
        
        if ($search) {
            $query->where(function($q) use ($search, $filter) {
                if ($filter === 'codigo') {
                    $q->where('codigo_rue', 'like', "%$search%");
                } elseif ($filter === 'departamento') {
                    $q->whereHas('ubicacion', function($q) use ($search) {
                        $q->where('departamento', 'like', "%$search%");
                    });
                } else {
                    $q->where('nombre', 'like', "%$search%");
                }
            });
        }
        
        $schools = $query->paginate(10)->appends($request->query());
        
        return view('welcome', compact('schools', 'search', 'filter'));
    
    }
    
    public function showDetails($id)
    {
        return "hola";
        $school = School::with(['ubicacion', 'servicios', 'ambientes'])->findOrFail($id);
        return view('schools.details', compact('school'));
    }

    /**
     * Display a listing of the resource.
     */
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSchoolRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(School $school)
    {
        $ubicaciones = $school->ubicacion;
        $servicios = $school->servicios;
        $ambientes = $school->ambientes;
        $estadisticas = $school->estadisticas;
        $history = app(\App\Services\EducationHistory::class)->school($estadisticas);
        $data= compact('school', 'ubicaciones', 'servicios', 'ambientes', 'estadisticas');
        //return $data;
        return view('schools.vista', compact('school', 'ubicaciones', 'servicios', 'ambientes','estadisticas', 'history'));
    
    }

    // Ejemplo de controlador para la vista
    public function probabilidad(Request $request)
    {
        $departamento = $request->get('departamento');
        $orden = $request->get('orden', 'desc');

        $query = School::with(['estadisticas', 'ubicacion']);
        if ($departamento) {
            $query->whereHas('ubicacion', function($q) use ($departamento) {
                $q->where('departamento', $departamento);
            });
        }
        // Calcula la probabilidad y ordena
        $schools = $query->get()->map(function($school) {
            $totalMatricula = $school->estadisticas->where('categoria', 'matricula')->sum('total');
            $totalReprobados = $school->estadisticas->where('categoria', 'reprobados')->sum('total');
            $probabilidadReprobarCalculada = $totalMatricula > 0 ? round(($totalReprobados / $totalMatricula) * 100, 2) : 0;
            return $school;
        });

        // Ordenar por probabilidad
        $schools = $orden == 'asc'
            ? $schools->sortBy('probabilidadReprobarCalculada')->values()
            : $schools->sortByDesc('probabilidadReprobarCalculada')->values();

        // Para el filtro de departamentos
        $departamentos = Ubicacion::select('departamento')->distinct()->pluck('departamento');
        return view('schools.probabilidad', compact('schools', 'departamentos'));
    }

    /**
     * Vista de estadísticas de reprobados
     */
    public function reprobados()
    {
        // Año y departamento seleccionados
        $anio = request('anio', now()->year);
        $departamento = request('departamento');

        // IDs de colegios filtrados por departamento (si aplica)
        $schoolIds = null;
        if ($departamento) {
            $schoolIds = \App\Models\Ubicacion::where('departamento', $departamento)->pluck('school_id');
        }

        // Promedio nacional o departamental
        $estadisticasQuery = \App\Models\Estadistica::where('categoria', 'reprobados')->where('anio', $anio);
        $matriculaQuery = \App\Models\Estadistica::where('categoria', 'matricula')->where('anio', $anio);
        if ($schoolIds) {
            $estadisticasQuery->whereIn('school_id', $schoolIds);
            $matriculaQuery->whereIn('school_id', $schoolIds);
        }
        $totalReprobados = $estadisticasQuery->sum('total');
        $totalMatricula = $matriculaQuery->sum('total');
        $promedioNacional = $totalMatricula > 0 ? round(($totalReprobados / $totalMatricula) * 100, 2) : 0;

        // Promedio por departamento (para el select)
        $departamentos = \App\Models\Ubicacion::select('departamento')->distinct()->pluck('departamento');
        $promedioPorDepartamento = [];
        foreach ($departamentos as $dep) {
            $depSchoolIds = \App\Models\Ubicacion::where('departamento', $dep)->pluck('school_id');
            $depReprobados = \App\Models\Estadistica::where('categoria', 'reprobados')->where('anio', $anio)->whereIn('school_id', $depSchoolIds)->sum('total');
            $depMatricula = \App\Models\Estadistica::where('categoria', 'matricula')->where('anio', $anio)->whereIn('school_id', $depSchoolIds)->sum('total');
            $promedioPorDepartamento[$dep] = $depMatricula > 0 ? round(($depReprobados / $depMatricula) * 100, 2) : 0;
        }

        // Promedio por género
        $reprobadosMujer = (clone $estadisticasQuery)->sum('mujer');
        $reprobadosHombre = (clone $estadisticasQuery)->sum('hombre');
        $matriculaMujer = (clone $matriculaQuery)->sum('mujer');
        $matriculaHombre = (clone $matriculaQuery)->sum('hombre');
        $promedioMujer = $matriculaMujer > 0 ? round(($reprobadosMujer / $matriculaMujer) * 100, 2) : 0;
        $promedioHombre = $matriculaHombre > 0 ? round(($reprobadosHombre / $matriculaHombre) * 100, 2) : 0;

        // Colegios filtrados por departamento (si aplica)
        $schoolsQuery = \App\Models\School::with(['estadisticas' => function($q) use ($anio) {
            $q->where('categoria', 'reprobados')->where('anio', $anio);
        }, 'ubicacion']);
        if ($schoolIds) {
            $schoolsQuery->whereIn('id', $schoolIds);
        }
        $schools = $schoolsQuery->get();

        // Top 10 más y menos reprobados (agregando probabilidad)
        $top10 = $schools->sortByDesc(function($school) {
            return $school->estadisticas->sum('total');
        })->take(10)->map(function($school) use ($anio) {
            $reprobados = $school->estadisticas->sum('total');
            $matricula = \App\Models\Estadistica::where('school_id', $school->id)->where('categoria', 'matricula')->where('anio', $anio)->sum('total');
            $probabilidad = $matricula > 0 ? round(($reprobados / $matricula) * 100, 2) : 0;
            $school->probabilidad = $probabilidad;
            return $school;
        });
        $bottom10 = $schools->sortBy(function($school) {
            return $school->estadisticas->sum('total');
        })->take(10)->map(function($school) use ($anio) {
            $reprobados = $school->estadisticas->sum('total');
            $matricula = \App\Models\Estadistica::where('school_id', $school->id)->where('categoria', 'matricula')->where('anio', $anio)->sum('total');
            $probabilidad = $matricula > 0 ? round(($reprobados / $matricula) * 100, 2) : 0;
            $school->probabilidad = $probabilidad;
            return $school;
        });

        // Colegio con más y menos reprobados (con probabilidad)
        $colegioMax = $top10->first();
        $colegioMin = $bottom10->first();

        // Reprobación por área (Rural/Urbana)
        $areas = ['URBANA', 'RURAL'];
        $reprobacionPorArea = [];
        foreach ($areas as $area) {
            $areaSchoolIds = \App\Models\Ubicacion::where('area', $area);
            if ($departamento) {
                $areaSchoolIds = $areaSchoolIds->where('departamento', $departamento);
            }
            $areaSchoolIds = $areaSchoolIds->pluck('school_id');
            $rep = \App\Models\Estadistica::where('categoria', 'reprobados')->where('anio', $anio)->whereIn('school_id', $areaSchoolIds)->sum('total');
            $mat = \App\Models\Estadistica::where('categoria', 'matricula')->where('anio', $anio)->whereIn('school_id', $areaSchoolIds)->sum('total');
            $reprobacionPorArea[$area] = $mat > 0 ? round(($rep / $mat) * 100, 2) : 0;
        }

        // Colegios para el mapa de calor (todos los del filtro)
        $colegiosMapa = $schools->map(function($school) use ($anio) {
            $ubic = $school->ubicacion;
            if (!$ubic || !$ubic->latitud || !$ubic->longitud) return null;
            $reprobados = $school->estadisticas->sum('total');
            $matricula = \App\Models\Estadistica::where('school_id', $school->id)->where('categoria', 'matricula')->where('anio', $anio)->sum('total');
            $probabilidad = $matricula > 0 ? round(($reprobados / $matricula) * 100, 2) : 0;
            return [
                'nombre' => $school->nombre,
                'rue' => $school->codigo_rue,
                'lat' => $ubic->latitud,
                'lng' => $ubic->longitud,
                'reprobados' => $reprobados,
                'probabilidad' => $probabilidad,
                'area' => $ubic->area ?? ''
            ];
        })->filter()->values();

        // Años disponibles para el select
        $aniosDisponibles = \App\Models\Estadistica::select('anio')->distinct()->orderBy('anio', 'desc')->pluck('anio');

        // Totales y porcentajes para tablas y gráficas
        $tablaTotales = [];
        $tablaGenero = [];
        $tablaArea = [];
        $tablaMunicipios = [];
        $tablaDistritos = [];
        if (!$departamento) {
            // Nacional: totales por departamento
            foreach ($departamentos as $dep) {
                $depSchoolIds = \App\Models\Ubicacion::where('departamento', $dep)->pluck('school_id');
                $rep = \App\Models\Estadistica::where('categoria', 'reprobados')->where('anio', $anio)->whereIn('school_id', $depSchoolIds)->sum('total');
                $mat = \App\Models\Estadistica::where('categoria', 'matricula')->where('anio', $anio)->whereIn('school_id', $depSchoolIds)->sum('total');
                $tablaTotales[] = [
                    'nombre' => $dep,
                    'reprobados' => $rep,
                    'matricula' => $mat,
                    'porcentaje' => $mat > 0 ? round(($rep/$mat)*100,2) : 0
                ];
            }
        } else {
            // Departamental: totales por provincia, municipio y distrito
            $provincias = \App\Models\Ubicacion::where('departamento', $departamento)->select('provincia')->distinct()->pluck('provincia');
            foreach ($provincias as $prov) {
                $provSchoolIds = \App\Models\Ubicacion::where('departamento', $departamento)->where('provincia', $prov)->pluck('school_id');
                $rep = \App\Models\Estadistica::where('categoria', 'reprobados')->where('anio', $anio)->whereIn('school_id', $provSchoolIds)->sum('total');
                $mat = \App\Models\Estadistica::where('categoria', 'matricula')->where('anio', $anio)->whereIn('school_id', $provSchoolIds)->sum('total');
                $tablaTotales[] = [
                    'nombre' => $prov,
                    'reprobados' => $rep,
                    'matricula' => $mat,
                    'porcentaje' => $mat > 0 ? round(($rep/$mat)*100,2) : 0
                ];
            }
            // Municipios
            $municipios = \App\Models\Ubicacion::where('departamento', $departamento)->select('municipio')->distinct()->pluck('municipio');
            $tablaMunicipios = [];
            foreach ($municipios as $mun) {
                $munSchoolIds = \App\Models\Ubicacion::where('departamento', $departamento)->where('municipio', $mun)->pluck('school_id');
                $rep = \App\Models\Estadistica::where('categoria', 'reprobados')->where('anio', $anio)->whereIn('school_id', $munSchoolIds)->sum('total');
                $mat = \App\Models\Estadistica::where('categoria', 'matricula')->where('anio', $anio)->whereIn('school_id', $munSchoolIds)->sum('total');
                $tablaMunicipios[] = [
                    'nombre' => $mun,
                    'reprobados' => $rep,
                    'matricula' => $mat,
                    'porcentaje' => $mat > 0 ? round(($rep/$mat)*100,2) : 0
                ];

            }
            usort($tablaMunicipios, fn($a, $b) => $b['reprobados'] <=> $a['reprobados']);


            // Distritos
            $distritos = \App\Models\Ubicacion::where('departamento', $departamento)->select('distrito')->distinct()->pluck('distrito');
            $tablaDistritos = [];
            foreach ($distritos as $dist) {
                $distSchoolIds = \App\Models\Ubicacion::where('departamento', $departamento)->where('distrito', $dist)->pluck('school_id');
                $rep = \App\Models\Estadistica::where('categoria', 'reprobados')->where('anio', $anio)->whereIn('school_id', $distSchoolIds)->sum('total');
                $mat = \App\Models\Estadistica::where('categoria', 'matricula')->where('anio', $anio)->whereIn('school_id', $distSchoolIds)->sum('total');
                $tablaDistritos[] = [
                    'nombre' => $dist,
                    'reprobados' => $rep,
                    'matricula' => $mat,
                    'porcentaje' => $mat > 0 ? round(($rep/$mat)*100,2) : 0
                ];
            }
        }
        // Por género
        $repMujer = (clone $estadisticasQuery)->sum('mujer');
        $repHombre = (clone $estadisticasQuery)->sum('hombre');
        $matMujer = (clone $matriculaQuery)->sum('mujer');
        $matHombre = (clone $matriculaQuery)->sum('hombre');
        $tablaGenero = [
            ['nombre' => 'Mujer', 'reprobados' => $repMujer, 'matricula' => $matMujer, 'porcentaje' => $matMujer > 0 ? round(($repMujer/$matMujer)*100,2) : 0],
            ['nombre' => 'Hombre', 'reprobados' => $repHombre, 'matricula' => $matHombre, 'porcentaje' => $matHombre > 0 ? round(($repHombre/$matHombre)*100,2) : 0],
        ];
        // Por área
        foreach (['URBANA', 'RURAL'] as $area) {
            $areaSchoolIds = \App\Models\Ubicacion::where('area', $area);
            if ($departamento) $areaSchoolIds = $areaSchoolIds->where('departamento', $departamento);
            $areaSchoolIds = $areaSchoolIds->pluck('school_id');
            $rep = \App\Models\Estadistica::where('categoria', 'reprobados')->where('anio', $anio)->whereIn('school_id', $areaSchoolIds)->sum('total');
            $mat = \App\Models\Estadistica::where('categoria', 'matricula')->where('anio', $anio)->whereIn('school_id', $areaSchoolIds)->sum('total');
            $tablaArea[] = [
                'nombre' => $area,
                'reprobados' => $rep,
                'matricula' => $mat,
                'porcentaje' => $mat > 0 ? round(($rep/$mat)*100,2) : 0
            ];
        }

        return view('reprobados.index', compact(
            'anio',
            'aniosDisponibles',
            'departamento',
            'promedioNacional',
            'promedioPorDepartamento',
            'promedioMujer',
            'promedioHombre',
            'colegioMax',
            'colegioMin',
            'top10',
            'bottom10',
            'departamentos',
            'reprobacionPorArea',
            'colegiosMapa',
            'tablaTotales',
            'tablaGenero',
            'tablaArea',
            'tablaMunicipios',
            'tablaDistritos'
        ));
    }

    /**
     * Corrige las estadísticas de matrícula faltantes usando los JSON por departamento
     */
    public function fixMatriculasFromJson()
    {
        $departamentos = [
            'BENI', 'Chuquisaca', 'Cochabamba', 'LaPaz', 'ORURO', 'PANDO', 'POTOSI', 'SANTACRUZ', 'TARIJA'
        ];
        $insertados = [];
        foreach ($departamentos as $dep) {
            $jsonFile = base_path("colegios_data_completo{$dep}.json");
            if (!file_exists($jsonFile)) {
                $insertados[$dep] = 'Archivo no encontrado';
                continue;
            }
            $json = json_decode(file_get_contents($jsonFile), true);
            if (!is_array($json)) {
                $insertados[$dep] = 'JSON vacío o mal formado';
                continue;
            }
            foreach ($json as $colegio) {
                $rue = $colegio['general']['codigo_rue'] ?? null;
                if (!$rue) continue;
                $school = \App\Models\School::where('codigo_rue', $rue)->first();
                
                if (!$school) continue;
                // Años disponibles en el JSON
                $anios = array_keys($colegio['estadisticas']['matricula']['Total.'] ?? []);
                foreach ($anios as $anio) {
                    $existe = \App\Models\Estadistica::where('school_id', $school->id)
                        ->where('categoria', 'matricula')
                        ->where('anio', $anio)
                        ->exists();
                    if (!$existe) {
                        $total = (int)str_replace('.', '', $colegio['estadisticas']['matricula']['Total.'][$anio] ?? 0);
                        $mujer = (int)str_replace('.', '', $colegio['estadisticas']['matricula']['Mujer'][$anio] ?? 0);
                        $hombre = (int)str_replace('.', '', $colegio['estadisticas']['matricula']['Hombre'][$anio] ?? 0);
                        \App\Models\Estadistica::create([
                            'school_id' => $school->id,
                            'categoria' => 'matricula',
                            'total' => $total,
                            'mujer' => $mujer,
                            'hombre' => $hombre,
                            'anio' => $anio
                        ]);
                        $insertados[$dep][$rue][] = $anio;
                    }
                }
            }
        }
        return response()->json([
            'status' => 'ok',
            'insertados' => $insertados
        ]);
    }


    
    public function rankingAplazados(Request $request)
    {
        // Puedes probar con una consulta simple primero
        $schools = \App\Models\School::with(['ubicacion', 'estadisticas'])->paginate(50);

        // Para evitar error, crea una vista básica temporal
        return view('schools.ranking_aplazados', compact('schools'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(School $school)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSchoolRequest $request, School $school)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(School $school)
    {
        //
    }
    public function densidadEducativa(Request $request)
    {
        // Get the most recent year with data
        $latestYear = \App\Models\Estadistica::where('categoria', 'matricula')
        ->max('anio') ?? 2023;
        
        $departamento = $request->get('departamento');
        $provincia = $request->get('provincia');
        
        
        if ($departamento && !$provincia) {
            // Show data by provinces within the selected department
            $results = Ubicacion::query()
                ->select('provincia')
                ->selectRaw('COUNT(DISTINCT schools.id) as cantidad_colegios')
                ->selectRaw('COALESCE(SUM(estadisticas.total), 0) as total_estudiantes')
                ->selectRaw('ROUND(COUNT(DISTINCT schools.id) * 100.0 / (SELECT COUNT(*) FROM schools), 2) as densidad')
                ->join('schools', 'ubicacions.school_id', '=', 'schools.id')
                ->leftJoin('estadisticas', function($join) use ($latestYear) {
                    $join->on('estadisticas.school_id', '=', 'schools.id')
                        ->where('estadisticas.categoria', '=', 'matricula')
                        ->where('estadisticas.anio', '=', $latestYear);
                })
                ->where('departamento', $departamento)
                ->groupBy('provincia')
                ->orderBy('cantidad_colegios', 'desc')
                ->get();
           
            // Calculate totals for the department
            $total_colegios = $results->sum('cantidad_colegios');
            $total_estudiantes = $results->sum('total_estudiantes');
            $densidad_promedio = $results->avg('densidad');
            $cantidad_provincias = $results->count();
            
            // Get all provinces for the filter
            $provincias = Ubicacion::where('departamento', $departamento)
                ->select('provincia')
                ->distinct()
                ->pluck('provincia');
                
        } elseif ($departamento && $provincia) {
            // Show data by municipalities within the selected province
            $results = Ubicacion::query()
                ->select('municipio')
                ->selectRaw('COUNT(DISTINCT schools.id) as cantidad_colegios')
                ->selectRaw('COALESCE(SUM(estadisticas.total), 0) as total_estudiantes')
                ->selectRaw('ROUND(COUNT(DISTINCT schools.id) * 100.0 / (SELECT COUNT(*) FROM schools), 2) as densidad')
                ->join('schools', 'ubicacions.school_id', '=', 'schools.id')
                ->leftJoin('estadisticas', function($join) use ($latestYear) {
                    $join->on('estadisticas.school_id', '=', 'schools.id')
                        ->where('estadisticas.categoria', '=', 'matricula')
                        ->where('estadisticas.anio', '=', $latestYear);
                })
                ->where('departamento', $departamento)
                ->where('provincia', $provincia)
                ->groupBy('municipio')
                ->orderBy('cantidad_colegios', 'desc')
                ->get();
            
            // Calculate totals for the province
            $total_colegios = $results->sum('cantidad_colegios');
            $total_estudiantes = $results->sum('total_estudiantes');
            $densidad_promedio = $results->avg('densidad');
            $cantidad_provincias = $results->count();
            
            // Get all provinces for the filter
            $provincias = Ubicacion::where('departamento', $departamento)
                ->select('provincia')
                ->distinct()
                ->pluck('provincia');
                
        } else {
            // Show data by departments (default view)
            $results = Ubicacion::query()
                ->select('departamento')
                ->selectRaw('COUNT(DISTINCT schools.id) as cantidad_colegios')
                ->selectRaw('COALESCE(SUM(estadisticas.total), 0) as total_estudiantes')
                ->selectRaw('ROUND(COUNT(DISTINCT schools.id) * 100.0 / (SELECT COUNT(*) FROM schools), 2) as densidad')
                ->join('schools', 'ubicacions.school_id', '=', 'schools.id')
                ->leftJoin('estadisticas', function($join) use ($latestYear) {
                    $join->on('estadisticas.school_id', '=', 'schools.id')
                        ->where('estadisticas.categoria', '=', 'matricula')
                        ->where('estadisticas.anio', '=', $latestYear);
                })
                ->groupBy('departamento')
                ->orderBy('cantidad_colegios', 'desc')
                ->get();
            
            // Calculate totals
            $total_colegios = $results->sum('cantidad_colegios');
            $total_estudiantes = $results->sum('total_estudiantes');
            $densidad_promedio = $results->avg('densidad');
            $cantidad_provincias = null;
            $provincias = collect();
        }
        
        // Get all departments for the filter
        $departamentos = Ubicacion::select('departamento')
            ->distinct()
            ->pluck('departamento');
        
        // Debug logging
        
        return view('schools.densidad', compact(
            'results', 
            'total_colegios', 
            'total_estudiantes', 
            'densidad_promedio',
            'cantidad_provincias',
            'latestYear', 
            'departamentos',
            'provincias',
            'departamento',
            'provincia'
        ));
    }

    /**
     * Debug method to check available data
     */
    public function debugDensityData()
    {
        $latestYear = \App\Models\Estadistica::where('categoria', 'matricula')
            ->max('anio') ?? 2023;
        
        // Test the density query
        $densityTest = \App\Models\Ubicacion::query()
            ->select('departamento')
            ->selectRaw('COUNT(DISTINCT schools.id) as cantidad_colegios')
            ->selectRaw('COALESCE(SUM(estadisticas.total), 0) as total_estudiantes')
            ->selectRaw('ROUND(COUNT(DISTINCT schools.id) * 100.0 / (SELECT COUNT(*) FROM schools), 2) as densidad')
            ->join('schools', 'ubicacions.school_id', '=', 'schools.id')
            ->leftJoin('estadisticas', function($join) use ($latestYear) {
                $join->on('estadisticas.school_id', '=', 'schools.id')
                    ->where('estadisticas.categoria', '=', 'matricula')
                    ->where('estadisticas.anio', '=', $latestYear);
            })
            ->groupBy('departamento')
            ->limit(5)
            ->get();
        
        return response()->json([
            'latestYear' => $latestYear,
            'densityTest' => $densityTest,
            'phpData' => [
                'results' => $densityTest,
                'total_colegios' => $densityTest->sum('cantidad_colegios'),
                'total_estudiantes' => $densityTest->sum('total_estudiantes'),
                'densidad_promedio' => $densityTest->avg('densidad'),
                'departamentos' => \App\Models\Ubicacion::select('departamento')->distinct()->pluck('departamento')
            ]
        ]);
    }
}
