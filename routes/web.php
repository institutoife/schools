<?php

use App\Http\Controllers\SchoolController;
use App\Http\Controllers\RankingsController;
use App\Http\Controllers\PanelAplazadosController;
use App\Http\Controllers\FiscalesPrivadosController;
use App\Models\School;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Route::get('/', function () {
    
//     return view('welcome');
// });
// Página principal con búsqueda
Route::get('/', [SchoolController::class, 'index'])->name('home');
Route::get('/historia-aplazados', \App\Http\Controllers\AplazadosHistoryController::class)->name('aplazados.historia');
Route::get('/reportes/acceder/{report}', function (string $report) {
    $target = config("report_access.$report");
    abort_unless($target, 404);

    if (! auth()->check()) {
        return redirect()->guest(url('/admin/login'));
    }

    return redirect()->to(url($target));
})->name('reports.access');
Route::get('/probabilidad', [SchoolController::class, 'probabilidad'])->name('probabilidad');

// Detalle del colegio (opcional)
Route::get('/schools/{school}', [SchoolController::class, 'show'])->name('schools.show');

Route::get('/schools/{id}', [\App\Http\Controllers\SchoolController::class, 'showDetails'])->name('school.details');

Route::get('/reprobados', [SchoolController::class, 'reprobados'])->name('reprobados.index');
Route::middleware(['auth'])->group(function () {
    // Las rutas dentro de este grupo requieren autenticación
    Route::get('/rankings', [RankingsController::class, 'index'])->name('rankings.index');
    Route::get('/api/schools/search', [RankingsController::class, 'searchSchools'])->name('schools.search');
    Route::get('/reprobados', [SchoolController::class, 'reprobados'])->name('reprobados.index');
    Route::get('/schools/{id}', [SchoolController::class, 'showDetails'])->name('school.details');
    Route::get('/probabilidad', [SchoolController::class, 'probabilidad'])->name('probabilidad');
    Route::get('/colegios/fix-matriculas', [SchoolController::class, 'fixMatriculasFromJson']);
    Route::get('/densidad-educativa', [SchoolController::class, 'densidadEducativa'])->name('densidad.educativa');
    Route::get('/debug-density-data', [SchoolController::class, 'debugDensityData'])->name('debug.density.data');
    Route::get('/test-densidad', function () { return view('schools.test-densidad'); });
    Route::get('/debug-densidad', function () { return view('schools.debug-densidad'); });
    Route::get('/test-main', function () { return view('schools.test-main'); });
    Route::get('ranking-aplazados', [SchoolController::class, 'rankingAplazados'])->name('ranking-aplazados');
    Route::get('comparar-municipios', [\App\Http\Controllers\MunicipioController::class, 'comparar'])->name('comparar-municipios');
    Route::get('listar-colegios-municipio', [\App\Http\Controllers\MunicipioController::class, 'listarColegios'])->name('listar-colegios-municipio');
    Route::get('colegios-por-municipio', [\App\Http\Controllers\MunicipioController::class, 'getColegiosPorMunicipio'])->name('colegios-por-municipio');
    Route::get('luisespinal', function () { return view('luisespinal'); })->name('grafico.luisespinal');
    Route::get('/municipios-aplazados', [\App\Http\Controllers\MunicipioController::class, 'masAplazadosPorMunicipio'])->name('municipios-aplazados');
    Route::get('/distritos-aplazados', [\App\Http\Controllers\MunicipioController::class, 'aplazadosPorDistrito'])->name('distritos-aplazados');
    Route::get('/distritos-municipales-aplazados', [\App\Http\Controllers\MunicipioController::class, 'aplazadosPorDistritoMunicipal'])->name('distritos-municipales-aplazados');
});

// Detalle del colegio debe ser público
// Nueva ruta: colegios con más reprobados
Route::get('/colegios-mas-reprobados', [RankingsController::class, 'colegiosMasReprobados'])->name('colegios.masreprobados');
// Nueva ruta: ranking personalizado de tu colegio
Route::get('/ranking-mi-colegio', [RankingsController::class, 'rankingMiColegio'])->name('ranking.micolegio');
Route::get('/schools/{school}', [SchoolController::class, 'show'])->name('schools.show');

Route::get('/colegios/fix-matriculas', [SchoolController::class, 'fixMatriculasFromJson']);

Route::get('/memory-test', function() {
    $usage = [];
    
    // Antes de la consulta
    $usage['start'] = memory_get_usage();
    
    // Consulta paginada
    $schools = School::paginate(15);
    $usage['after_query'] = memory_get_usage();
    
    return response()->json([
        'memory_usage' => [
            'start' => $usage['start'],
            'after_query' => $usage['after_query'],
            'difference' => $usage['after_query'] - $usage['start']
        ],
        'data_count' => $schools->count(),
        'total_records' => $schools->total()
    ]);
});

Route::get('/densidad-educativa', [SchoolController::class, 'densidadEducativa'])
    ->name('densidad.educativa');

Route::get('/debug-density-data', [SchoolController::class, 'debugDensityData'])
    ->name('debug.density.data');
Route::get('/test-densidad', function () {
    return view('schools.test-densidad');
});
Route::get('/debug-densidad', function () {
    return view('schools.debug-densidad');
});

Route::get('/test-main', function () {
    return view('schools.test-main');
});


Route::get("ranking-aplazados", [SchoolController::class, "rankingAplazados"])->name('ranking-aplazados');

Route::get('/test-data', function () {
    $latestYear = \App\Models\Estadistica::where('categoria', 'matricula')
        ->max('anio') ?? 2023;
    
    $results = \App\Models\Ubicacion::query()
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
    
    return response()->json([
        'latestYear' => $latestYear,
        'results' => $results,
        'total_colegios' => $results->sum('cantidad_colegios'),
        'total_estudiantes' => $results->sum('total_estudiantes'),
        'densidad_promedio' => $results->avg('densidad'),
        'count' => $results->count()
    ]);
});

Route::get('comparar-municipios', [\App\Http\Controllers\MunicipioController::class, 'comparar'])->name('comparar-municipios');
Route::get('listar-colegios-municipio', [\App\Http\Controllers\MunicipioController::class, 'listarColegios'])->name('listar-colegios-municipio');
Route::get('colegios-por-municipio', [\App\Http\Controllers\MunicipioController::class, 'getColegiosPorMunicipio'])->name('colegios-por-municipio');
Route::get('/municipios-aplazados', [\App\Http\Controllers\MunicipioController::class, 'masAplazadosPorMunicipio'])->name('municipios-aplazados');

Route::get('luisespinal', function () {
    return view('luisespinal');
})->name('grafico.luisespinal');

Route::get('/panel-aplazados-calor', [PanelAplazadosController::class, 'index'])->name('panel.aplazados');

Route::get('/panel-aplazados-calor/opciones-ubicacion', [PanelAplazadosController::class, 'opcionesUbicacion'])->name('panel.aplazados.opciones');

Route::get('/panel-aplazados-calor/centros-poblados', [PanelAplazadosController::class, 'centrosPoblados'])->name('panel.aplazados.centros');

// Ruta para análisis de fiscales vs privados
Route::get('/fiscales-privados', [\App\Http\Controllers\FiscalesPrivadosController::class, 'index'])->name('fiscales-privados.index');
Route::get('/api/fiscales-privados', [\App\Http\Controllers\FiscalesPrivadosController::class, 'obtenerDatos'])->name('fiscales-privados.datos');
Route::get('/api/fiscales-privados/opciones', [\App\Http\Controllers\FiscalesPrivadosController::class, 'opcionesUbicacion'])->name('fiscales-privados.opciones');
