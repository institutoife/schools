<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\Estadistica;
use App\Models\Ubicacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FiscalesPrivadosController extends Controller
{
    public function index(Request $request)
    {
        $departamento = $this->normalizeFilter($request->get('departamento'));
        $provincia = $this->normalizeFilter($request->get('provincia'));
        $municipio = $this->normalizeFilter($request->get('municipio'));
        $distrito = $this->normalizeFilter($request->get('distrito'));

        // Obtener año más reciente con datos
        $latestYear = Estadistica::where('categoria', 'matricula')->max('anio');

        $datos = [];

        // Obtener datos según filtros
        $datos = $this->datosPorDependencia($departamento, $provincia, $municipio, $distrito, null, $latestYear);

        // Obtener opciones de ubicación para los selectores
        $ubicacionOptions = $this->buildUbicacionOptions($departamento, $provincia, $municipio);

        $departamentos = $ubicacionOptions['departamentos'];
        $provincias = $ubicacionOptions['provincias'];
        $municipios = $ubicacionOptions['municipios'];
        $distritos = $ubicacionOptions['distritos'];

        return view('schools.fiscales_privados', compact(
            'datos',
            'departamento',
            'provincia',
            'municipio',
            'distrito',
            'departamentos',
            'provincias',
            'municipios',
            'distritos',
            'latestYear'
        ));
    }

    /**
     * Obtiene datos agrupados por dependencia (fiscal/privado)
     */
    private function datosPorDependencia($departamento = null, $provincia = null, $municipio = null, $distrito = null, $circunscripcion = null, $latestYear = null)
    {
        $fiscal = [
            'nombre' => 'Fiscales',
            'cantidad_colegios' => 0,
            'matricula' => 0,
            'reprobados' => 0,
            'abandono' => 0,
            'aprobados' => 0,
            'porcentaje_reprobacion' => 0,
            'porcentaje_abandono' => 0,
        ];

        $privado = [
            'nombre' => 'Privados',
            'cantidad_colegios' => 0,
            'matricula' => 0,
            'reprobados' => 0,
            'abandono' => 0,
            'aprobados' => 0,
            'porcentaje_reprobacion' => 0,
            'porcentaje_abandono' => 0,
        ];

        // Query base para colegios
        $schoolsQuery = School::query();

        if ($departamento || $provincia || $municipio || $distrito || $circunscripcion) {
            $schoolsQuery->whereHas('ubicacion', function($q) use ($departamento, $provincia, $municipio, $distrito, $circunscripcion) {
                if ($departamento) $q->where('departamento', $departamento);
                if ($provincia) $q->where('provincia', $provincia);
                if ($municipio) $q->where('municipio', $municipio);
                if ($distrito) $q->where('distrito', $distrito);
                if ($circunscripcion) $q->where('circunscripcion', $circunscripcion);
            });
        }

        // Procesar fiscales
        $colegiosFiscales = (clone $schoolsQuery)->where('dependencia', 'fiscal')->get();
        $fiscal['cantidad_colegios'] = $colegiosFiscales->count();

        $datosEstaFiscal = $this->obtenerEstadisticas($colegiosFiscales->pluck('id'), $latestYear);
        $fiscal['matricula'] = $datosEstaFiscal['matricula'];
        $fiscal['reprobados'] = $datosEstaFiscal['reprobados'];
        $fiscal['abandono'] = $datosEstaFiscal['abandono'];
        $fiscal['aprobados'] = $datosEstaFiscal['aprobados'];

        if ($fiscal['matricula'] > 0) {
            $fiscal['porcentaje_reprobacion'] = round(($fiscal['reprobados'] / $fiscal['matricula']) * 100, 2);
            $fiscal['porcentaje_abandono'] = round(($fiscal['abandono'] / $fiscal['matricula']) * 100, 2);
        }

        // Procesar privados
        $colegiosPrivados = (clone $schoolsQuery)->where('dependencia', 'privado')->get();
        $privado['cantidad_colegios'] = $colegiosPrivados->count();

        $datosEstaPrivado = $this->obtenerEstadisticas($colegiosPrivados->pluck('id'), $latestYear);
        $privado['matricula'] = $datosEstaPrivado['matricula'];
        $privado['reprobados'] = $datosEstaPrivado['reprobados'];
        $privado['abandono'] = $datosEstaPrivado['abandono'];
        $privado['aprobados'] = $datosEstaPrivado['aprobados'];

        if ($privado['matricula'] > 0) {
            $privado['porcentaje_reprobacion'] = round(($privado['reprobados'] / $privado['matricula']) * 100, 2);
            $privado['porcentaje_abandono'] = round(($privado['abandono'] / $privado['matricula']) * 100, 2);
        }

        return [
            'fiscal' => $fiscal,
            'privado' => $privado,
        ];
    }

    /**
     * Obtiene estadísticas para un conjunto de colegios
     */
    private function obtenerEstadisticas($schoolIds, $latestYear = null)
    {
        // Crear queries separadas para cada categoría (no reutilizar $query)
        $baseQuery = function() use ($schoolIds, $latestYear) {
            $q = Estadistica::whereIn('school_id', $schoolIds);
            if ($latestYear) {
                $q->where('anio', $latestYear);
            }
            return $q;
        };

        $matricula = $baseQuery()->where('categoria', 'matricula')->sum('total');
        $reprobados = $baseQuery()->where('categoria', 'reprobados')->sum('total');
        $abandono = $baseQuery()->where('categoria', 'abandono')->sum('total');
        $aprobados = max(0, $matricula - $reprobados - $abandono);

        return [
            'matricula' => (int)$matricula,
            'reprobados' => (int)$reprobados,
            'abandono' => (int)$abandono,
            'aprobados' => (int)$aprobados,
        ];
    }

    /**
     * API para obtener datos por nivel
     */
    public function obtenerDatos(Request $request)
    {
        $departamento = $this->normalizeFilter($request->get('departamento'));
        $provincia = $this->normalizeFilter($request->get('provincia'));
        $municipio = $this->normalizeFilter($request->get('municipio'));
        $distrito = $this->normalizeFilter($request->get('distrito'));

        $latestYear = Estadistica::where('categoria', 'matricula')->max('anio');

        $datos = $this->datosPorDependencia($departamento, $provincia, $municipio, $distrito, null, $latestYear);

        return response()->json($datos);
    }

    /**
     * API para obtener opciones de ubicación cascading
     */
    public function opcionesUbicacion(Request $request)
    {
        $departamento = $this->normalizeFilter($request->get('departamento'));
        $provincia = $this->normalizeFilter($request->get('provincia'));
        $municipio = $this->normalizeFilter($request->get('municipio'));

        return response()->json(
            $this->buildUbicacionOptions($departamento, $provincia, $municipio)
        );
    }

    /**
     * Normaliza los valores de filtro
     */
    private function normalizeFilter($value)
    {
        return (string) ($value ?? '');
    }

    /**
     * Construye las opciones de ubicación para los selectores cascading
     */
    private function buildUbicacionOptions($departamento = '', $provincia = '', $municipio = '')
    {
        $departamentos = Ubicacion::select('departamento')
            ->distinct()
            ->orderBy('departamento')
            ->pluck('departamento')
            ->toArray();

        $provincias = [];
        $municipios = [];
        $distritos = [];

        if ($departamento !== '') {
            $provincias = Ubicacion::where('departamento', $departamento)
                ->select('provincia')
                ->distinct()
                ->orderBy('provincia')
                ->pluck('provincia')
                ->toArray();

            if ($provincia !== '') {
                $municipios = Ubicacion::where('departamento', $departamento)
                    ->where('provincia', $provincia)
                    ->select('municipio')
                    ->distinct()
                    ->orderBy('municipio')
                    ->pluck('municipio')
                    ->toArray();

                if ($municipio !== '') {
                    $distritos = Ubicacion::where('departamento', $departamento)
                        ->where('provincia', $provincia)
                        ->where('municipio', $municipio)
                        ->select('distrito')
                        ->distinct()
                        ->orderBy('distrito')
                        ->pluck('distrito')
                        ->toArray();
                }
            }
        }

        return compact('departamentos', 'provincias', 'municipios', 'distritos');
    }
}
