@extends('layouts.app')

@section('content')
<div class="bg-primary-50 min-h-screen py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Encabezado -->
        <div class="mb-8">
            <h1 class="text-4xl sm:text-5xl font-bold text-secondary-800 mb-4">
                Análisis Fiscales vs Privados
            </h1>
            <p class="text-xl text-secondary-600 max-w-3xl">
                Comparativa de desempeño y estadísticas de colegios fiscales y privados en Bolivia. 
                Explora los datos por nivel geográfico para identificar diferencias en reprobación y abandono.
            </p>
        </div>

        <!-- Filtros -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8 border border-primary-100">
            <form id="filtros-form" method="GET" action="{{ route('fiscales-privados.index') }}" data-opciones-url="{{ route('fiscales-privados.opciones') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Departamento</label>
                    <select id="filtro-departamento" name="departamento" class="w-full border border-primary-200 rounded-lg px-4 py-2 focus:ring-2 focus:ring-primary-400 outline-none">
                        <option value="">TODOS</option>
                        @foreach($departamentos as $dept)
                            <option value="{{ $dept }}" {{ $departamento === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Provincia</label>
                    <select id="filtro-provincia" name="provincia" class="w-full border border-primary-200 rounded-lg px-4 py-2 focus:ring-2 focus:ring-primary-400 outline-none">
                        <option value="">TODOS</option>
                        @foreach($provincias as $prov)
                            <option value="{{ $prov }}" {{ $provincia === $prov ? 'selected' : '' }}>{{ $prov }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Municipio</label>
                    <select id="filtro-municipio" name="municipio" class="w-full border border-primary-200 rounded-lg px-4 py-2 focus:ring-2 focus:ring-primary-400 outline-none">
                        <option value="">TODOS</option>
                        @foreach($municipios as $mun)
                            <option value="{{ $mun }}" {{ $municipio === $mun ? 'selected' : '' }}>{{ $mun }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Distrito</label>
                    <select id="filtro-distrito" name="distrito" class="w-full border border-primary-200 rounded-lg px-4 py-2 focus:ring-2 focus:ring-primary-400 outline-none">
                        <option value="">TODOS</option>
                        @foreach($distritos as $dist)
                            <option value="{{ $dist }}" {{ $distrito === $dist ? 'selected' : '' }}>{{ $dist }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-lg px-6 py-2 transition">
                        Filtrar
                    </button>
                    <a href="{{ route('fiscales-privados.index') }}" class="w-full text-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold rounded-lg px-4 py-2 transition">
                        Limpiar
                    </a>
                </div>
            </form>

            @if($latestYear)
            <div class="mt-4 p-4 bg-primary-50 rounded-lg border border-primary-200">
                <p class="text-sm text-secondary-600"><strong>Año de datos:</strong> {{ $latestYear }}</p>
            </div>
            @endif
        </div>

        <!-- Resultados -->
        @if(!empty($datos) && !empty($datos['fiscal']))
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
            <!-- Tarjeta Fiscales -->
            <div class="bg-white rounded-xl shadow-lg p-8 border border-blue-200">
                <div class="flex items-center gap-4 mb-6">
                    <div class="p-4 bg-blue-100 rounded-xl">
                        <i class="fas fa-school text-3xl text-blue-600"></i>
                    </div>
                    <h2 class="text-3xl font-bold text-secondary-800">Colegios Fiscales</h2>
                </div>

                <div class="space-y-4">
                    <div class="flex justify-between items-center p-4 bg-blue-50 rounded-lg">
                        <span class="text-secondary-700 font-semibold">Cantidad de colegios:</span>
                        <span class="text-2xl font-bold text-blue-600">{{ number_format($datos['fiscal']['cantidad_colegios']) }}</span>
                    </div>
                    <div class="flex justify-between items-center p-4 bg-blue-50 rounded-lg">
                        <span class="text-secondary-700 font-semibold">Total matriculados:</span>
                        <span class="text-2xl font-bold text-blue-600">{{ number_format($datos['fiscal']['matricula']) }}</span>
                    </div>
                    <div class="flex justify-between items-center p-4 bg-green-50 rounded-lg">
                        <span class="text-secondary-700 font-semibold">Aprobados:</span>
                        <span class="text-2xl font-bold text-green-600">{{ number_format($datos['fiscal']['aprobados']) }} ({{ number_format(100 - $datos['fiscal']['porcentaje_reprobacion'] - $datos['fiscal']['porcentaje_abandono'], 2) }}%)</span>
                    </div>
                    <div class="flex justify-between items-center p-4 bg-red-50 rounded-lg">
                        <span class="text-secondary-700 font-semibold">Reprobados:</span>
                        <span class="text-2xl font-bold text-red-600">{{ number_format($datos['fiscal']['reprobados']) }} ({{ $datos['fiscal']['porcentaje_reprobacion'] }}%)</span>
                    </div>
                    <div class="flex justify-between items-center p-4 bg-orange-50 rounded-lg">
                        <span class="text-secondary-700 font-semibold">Abandono:</span>
                        <span class="text-2xl font-bold text-orange-600">{{ number_format($datos['fiscal']['abandono']) }} ({{ $datos['fiscal']['porcentaje_abandono'] }}%)</span>
                    </div>
                </div>

                <!-- Gráfico simple -->
                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                    <h3 class="text-sm font-semibold text-secondary-700 mb-3">Distribución de resultados</h3>
                    <div class="flex gap-1 h-8 rounded-lg overflow-hidden">
                        @php
                            $total = $datos['fiscal']['aprobados'] + $datos['fiscal']['reprobados'] + $datos['fiscal']['abandono'];
                            $aprob = $total > 0 ? ($datos['fiscal']['aprobados'] / $total * 100) : 0;
                            $reprob = $total > 0 ? ($datos['fiscal']['reprobados'] / $total * 100) : 0;
                            $abandon = $total > 0 ? ($datos['fiscal']['abandono'] / $total * 100) : 0;
                        @endphp
                        @if($aprob > 1)<div class="bg-green-500" style="width: {{ $aprob }}%"></div>@endif
                        @if($reprob > 1)<div class="bg-red-500" style="width: {{ $reprob }}%"></div>@endif
                        @if($abandon > 1)<div class="bg-orange-500" style="width: {{ $abandon }}%"></div>@endif
                    </div>
                    <div class="flex gap-4 mt-2 text-xs">
                        <span><span class="inline-block w-3 h-3 bg-green-500 rounded-sm"></span> Aprobados</span>
                        <span><span class="inline-block w-3 h-3 bg-red-500 rounded-sm"></span> Reprobados</span>
                        <span><span class="inline-block w-3 h-3 bg-orange-500 rounded-sm"></span> Abandono</span>
                    </div>
                </div>
            </div>

            <!-- Tarjeta Privados -->
            <div class="bg-white rounded-xl shadow-lg p-8 border border-purple-200">
                <div class="flex items-center gap-4 mb-6">
                    <div class="p-4 bg-purple-100 rounded-xl">
                        <i class="fas fa-graduation-cap text-3xl text-purple-600"></i>
                    </div>
                    <h2 class="text-3xl font-bold text-secondary-800">Colegios Privados</h2>
                </div>

                <div class="space-y-4">
                    <div class="flex justify-between items-center p-4 bg-purple-50 rounded-lg">
                        <span class="text-secondary-700 font-semibold">Cantidad de colegios:</span>
                        <span class="text-2xl font-bold text-purple-600">{{ number_format($datos['privado']['cantidad_colegios']) }}</span>
                    </div>
                    <div class="flex justify-between items-center p-4 bg-purple-50 rounded-lg">
                        <span class="text-secondary-700 font-semibold">Total matriculados:</span>
                        <span class="text-2xl font-bold text-purple-600">{{ number_format($datos['privado']['matricula']) }}</span>
                    </div>
                    <div class="flex justify-between items-center p-4 bg-green-50 rounded-lg">
                        <span class="text-secondary-700 font-semibold">Aprobados:</span>
                        <span class="text-2xl font-bold text-green-600">{{ number_format($datos['privado']['aprobados']) }} ({{ number_format(100 - $datos['privado']['porcentaje_reprobacion'] - $datos['privado']['porcentaje_abandono'], 2) }}%)</span>
                    </div>
                    <div class="flex justify-between items-center p-4 bg-red-50 rounded-lg">
                        <span class="text-secondary-700 font-semibold">Reprobados:</span>
                        <span class="text-2xl font-bold text-red-600">{{ number_format($datos['privado']['reprobados']) }} ({{ $datos['privado']['porcentaje_reprobacion'] }}%)</span>
                    </div>
                    <div class="flex justify-between items-center p-4 bg-orange-50 rounded-lg">
                        <span class="text-secondary-700 font-semibold">Abandono:</span>
                        <span class="text-2xl font-bold text-orange-600">{{ number_format($datos['privado']['abandono']) }} ({{ $datos['privado']['porcentaje_abandono'] }}%)</span>
                    </div>
                </div>

                <!-- Gráfico simple -->
                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                    <h3 class="text-sm font-semibold text-secondary-700 mb-3">Distribución de resultados</h3>
                    <div class="flex gap-1 h-8 rounded-lg overflow-hidden">
                        @php
                            $total2 = $datos['privado']['aprobados'] + $datos['privado']['reprobados'] + $datos['privado']['abandono'];
                            $aprob2 = $total2 > 0 ? ($datos['privado']['aprobados'] / $total2 * 100) : 0;
                            $reprob2 = $total2 > 0 ? ($datos['privado']['reprobados'] / $total2 * 100) : 0;
                            $abandon2 = $total2 > 0 ? ($datos['privado']['abandono'] / $total2 * 100) : 0;
                        @endphp
                        @if($aprob2 > 1)<div class="bg-green-500" style="width: {{ $aprob2 }}%"></div>@endif
                        @if($reprob2 > 1)<div class="bg-red-500" style="width: {{ $reprob2 }}%"></div>@endif
                        @if($abandon2 > 1)<div class="bg-orange-500" style="width: {{ $abandon2 }}%"></div>@endif
                    </div>
                    <div class="flex gap-4 mt-2 text-xs">
                        <span><span class="inline-block w-3 h-3 bg-green-500 rounded-sm"></span> Aprobados</span>
                        <span><span class="inline-block w-3 h-3 bg-red-500 rounded-sm"></span> Reprobados</span>
                        <span><span class="inline-block w-3 h-3 bg-orange-500 rounded-sm"></span> Abandono</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla comparativa -->
        <div class="bg-white rounded-xl shadow-lg p-8 border border-primary-100 overflow-x-auto">
            <h2 class="text-2xl font-bold text-secondary-800 mb-6">Comparativa detallada</h2>
            
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-primary-200">
                        <th class="text-left py-4 px-4 font-bold text-secondary-800">Indicador</th>
                        <th class="text-right py-4 px-4 font-bold text-blue-600">Fiscales</th>
                        <th class="text-right py-4 px-4 font-bold text-purple-600">Privados</th>
                        <th class="text-right py-4 px-4 font-bold text-secondary-700">Diferencia</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-primary-100 hover:bg-primary-50">
                        <td class="py-4 px-4">Colegios</td>
                        <td class="text-right py-4 px-4 font-semibold">{{ number_format($datos['fiscal']['cantidad_colegios']) }}</td>
                        <td class="text-right py-4 px-4 font-semibold">{{ number_format($datos['privado']['cantidad_colegios']) }}</td>
                        <td class="text-right py-4 px-4 font-semibold">{{ number_format($datos['fiscal']['cantidad_colegios'] - $datos['privado']['cantidad_colegios']) }}</td>
                    </tr>
                    <tr class="border-b border-primary-100 hover:bg-primary-50">
                        <td class="py-4 px-4">Matriculados</td>
                        <td class="text-right py-4 px-4 font-semibold">{{ number_format($datos['fiscal']['matricula']) }}</td>
                        <td class="text-right py-4 px-4 font-semibold">{{ number_format($datos['privado']['matricula']) }}</td>
                        <td class="text-right py-4 px-4 font-semibold">{{ number_format($datos['fiscal']['matricula'] - $datos['privado']['matricula']) }}</td>
                    </tr>
                    <tr class="border-b border-primary-100 hover:bg-primary-50 bg-red-50">
                        <td class="py-4 px-4 font-semibold">% Reprobación</td>
                        <td class="text-right py-4 px-4 font-bold text-red-600">{{ $datos['fiscal']['porcentaje_reprobacion'] }}%</td>
                        <td class="text-right py-4 px-4 font-bold text-red-600">{{ $datos['privado']['porcentaje_reprobacion'] }}%</td>
                        <td class="text-right py-4 px-4 font-bold {{ ($datos['fiscal']['porcentaje_reprobacion'] - $datos['privado']['porcentaje_reprobacion']) > 0 ? 'text-red-600' : 'text-green-600' }}">
                            {{ number_format($datos['fiscal']['porcentaje_reprobacion'] - $datos['privado']['porcentaje_reprobacion'], 2) }}%
                        </td>
                    </tr>
                    <tr class="border-b border-primary-100 hover:bg-primary-50 bg-orange-50">
                        <td class="py-4 px-4 font-semibold">% Abandono</td>
                        <td class="text-right py-4 px-4 font-bold text-orange-600">{{ $datos['fiscal']['porcentaje_abandono'] }}%</td>
                        <td class="text-right py-4 px-4 font-bold text-orange-600">{{ $datos['privado']['porcentaje_abandono'] }}%</td>
                        <td class="text-right py-4 px-4 font-bold {{ ($datos['fiscal']['porcentaje_abandono'] - $datos['privado']['porcentaje_abandono']) > 0 ? 'text-red-600' : 'text-green-600' }}">
                            {{ number_format($datos['fiscal']['porcentaje_abandono'] - $datos['privado']['porcentaje_abandono'], 2) }}%
                        </td>
                    </tr>
                    <tr class="hover:bg-primary-50 bg-green-50">
                        <td class="py-4 px-4 font-semibold">% Aprobación</td>
                        <td class="text-right py-4 px-4 font-bold text-green-600">{{ number_format(100 - $datos['fiscal']['porcentaje_reprobacion'] - $datos['fiscal']['porcentaje_abandono'], 2) }}%</td>
                        <td class="text-right py-4 px-4 font-bold text-green-600">{{ number_format(100 - $datos['privado']['porcentaje_reprobacion'] - $datos['privado']['porcentaje_abandono'], 2) }}%</td>
                        <td class="text-right py-4 px-4 font-bold">
                            {{ number_format((100 - $datos['fiscal']['porcentaje_reprobacion'] - $datos['fiscal']['porcentaje_abandono']) - (100 - $datos['privado']['porcentaje_reprobacion'] - $datos['privado']['porcentaje_abandono']), 2) }}%
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        @else
        <div class="bg-white rounded-xl shadow-lg p-12 text-center border border-primary-100">
            <i class="fas fa-info-circle text-5xl text-primary-400 mb-4"></i>
            <p class="text-lg text-secondary-600">
                Selecciona parámetros en los filtros para ver el análisis de fiscales vs privados.
            </p>
        </div>
        @endif
    </div>
</div>

<script>
    (function () {
        const form = document.getElementById('filtros-form');
        const endpoint = form?.dataset?.opcionesUrl;
        const departamentoSelect = document.getElementById('filtro-departamento');
        const provinciaSelect = document.getElementById('filtro-provincia');
        const municipioSelect = document.getElementById('filtro-municipio');
        const distritoSelect = document.getElementById('filtro-distrito');

        async function fetchOptions(departamento = '', provincia = '', municipio = '') {
            if (!endpoint) {
                return { departamentos: [], provincias: [], municipios: [], distritos: [] };
            }

            const params = new URLSearchParams({ departamento, provincia, municipio });
            const response = await fetch(`${endpoint}?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) {
                return { departamentos: [], provincias: [], municipios: [], distritos: [] };
            }

            return response.json();
        }

        function fillSelect(select, items, selectedValue = '') {
            if (!select) return;

            const unique = [...new Set((items || []).filter(Boolean))];
            select.innerHTML = '';

            const allOption = document.createElement('option');
            allOption.value = '';
            allOption.textContent = 'TODOS';
            select.appendChild(allOption);

            unique.forEach((item) => {
                const option = document.createElement('option');
                option.value = item;
                option.textContent = item;
                if (selectedValue && item === selectedValue) {
                    option.selected = true;
                }
                select.appendChild(option);
            });

            if (!selectedValue) {
                select.value = '';
            }
        }

        async function refreshCascade() {
            const departamento = departamentoSelect?.value || '';
            const provincia = provinciaSelect?.value || '';
            const municipio = municipioSelect?.value || '';

            const options = await fetchOptions(departamento, provincia, municipio);

            fillSelect(provinciaSelect, options.provincias, provincia);
            fillSelect(municipioSelect, options.municipios, municipio);
            fillSelect(distritoSelect, options.distritos, '');
        }

        departamentoSelect?.addEventListener('change', refreshCascade);
        provinciaSelect?.addEventListener('change', refreshCascade);
        municipioSelect?.addEventListener('change', refreshCascade);
    })();
</script>
@endsection
