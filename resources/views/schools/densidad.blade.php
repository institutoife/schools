<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Densidad - Colegios de Bolivia</title>
    <link rel="icon" href="{{ asset(config('brand.icon')) }}" type="image/png">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="{{ asset('css/vistas/schools/densidad.css') }}">
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <h1><i class="fas fa-chart-area"></i> Dashboard de Densidad</h1>
            <p>Análisis interactivo de la distribución de colegios en Bolivia - Datos del año {{ $latestYear ?? '2023' }}</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid" id="stats-grid">
            <!-- Stats will be populated by JavaScript -->
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <h3><i class="fas fa-filter"></i> Filtros</h3>
            <div class="filter-controls">
                <div class="filter-group">
                    <label for="departamento-filter">Departamento</label>
                    <select id="departamento-filter" name="departamento">
                        <option value="">Todos los departamentos</option>
                        @foreach($departamentos as $dept)
                            <option value="{{ $dept }}" {{ $departamento == $dept ? 'selected' : '' }}>
                                {{ $dept }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @if($departamento && $provincias->count() > 0)
                <div class="filter-group">
                    <label for="provincia-filter">Provincia</label>
                    <select id="provincia-filter" name="provincia">
                        <option value="">Todas las provincias</option>
                        @foreach($provincias as $prov)
                            <option value="{{ $prov }}" {{ $provincia == $prov ? 'selected' : '' }}>
                                {{ $prov }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="filter-group">
                    <label for="dependencia-filter">Dependencia</label>
                    <select id="dependencia-filter">
                        <option value="">Todas las dependencias</option>
                        <option value="fiscal">Fiscal</option>
                        <option value="privado">Privado</option>
                        <option value="convenio">Convenio</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="area-filter">Área</label>
                    <select id="area-filter">
                        <option value="">Todas las áreas</option>
                        <option value="urbana">Urbana</option>
                        <option value="rural">Rural</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button id="reset-filters" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Limpiar Filtros
                    </button>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button id="export-data" class="btn btn-primary">
                        <i class="fas fa-download"></i> Exportar Datos
                    </button>
                </div>
            </div>
        </div>

                 <!-- Heatmap Section -->
         <div class="heatmap-container">
             <h3><i class="fas fa-fire"></i> Mapa de Calor por {{ $departamento ? 'Provincias' : 'Departamentos' }}</h3>
             <div class="heatmap-grid" id="heatmap-grid">
                 <!-- Heatmap items will be populated by JavaScript -->
             </div>
         </div>

                 <!-- Charts Grid -->
         <div class="charts-grid">
             <!-- Bar Chart -->
             <div class="chart-container">
                 <h3><i class="fas fa-chart-bar"></i> Distribución de Colegios por {{ $departamento ? 'Provincia' : 'Departamento' }}</h3>
                 <div class="chart-wrapper">
                     <canvas id="bar-chart"></canvas>
                 </div>
             </div>

             <!-- Pie Chart -->
             <div class="chart-container">
                 <h3><i class="fas fa-chart-pie"></i> Proporción de Colegios por {{ $departamento ? 'Provincia' : 'Departamento' }}</h3>
                 <div class="chart-wrapper">
                     <canvas id="pie-chart"></canvas>
                 </div>
             </div>
         </div>

        <!-- Bolivia Map -->
        <div class="map-container">
            <h3><i class="fas fa-map-marked-alt"></i> Mapa Interactivo de Bolivia</h3>
            <div class="bolivia-map" id="bolivia-map">
                <!-- Map will be rendered by Leaflet -->
            </div>
        </div>

        <!-- Data Table -->
        <div class="chart-container">
            <h3><i class="fas fa-table"></i> Datos Detallados</h3>
            <div class="table-wrapper">
                                 <table class="data-table">
                     <thead>
                         <tr>
                             <th>{{ $departamento ? 'Provincia' : 'Departamento' }}</th>
                             <th>Cantidad de Colegios</th>
                             <th>Total de Estudiantes</th>
                             <th>Densidad</th>
                             <th>Porcentaje</th>
                         </tr>
                     </thead>
                     <tbody id="data-table-body">
                         @foreach($results as $item)
                         <tr>
                             <td>{{ $departamento ? $item->provincia : $item->departamento }}</td>
                             <td>{{ number_format($item->cantidad_colegios) }}</td>
                             <td>{{ number_format($item->total_estudiantes) }}</td>
                             <td>{{ $item->densidad ?? 'N/A' }}</td>
                             <td>{{ number_format(($item->cantidad_colegios / $total_colegios) * 100, 1) }}%</td>
                         </tr>
                         @endforeach
                     </tbody>
                 </table>
            </div>
        </div>
    </div>

    <!-- Leaflet JavaScript -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="{{ asset('js/vistas/schools/densidad.js') }}"></script>

    <script>
        // Pass PHP data to JavaScript
        window.phpData = {
            results: @json($results),
            total_colegios: {{ $total_colegios ?? 0 }},
            total_estudiantes: {{ $total_estudiantes ?? 0 }},
            densidad_promedio: {{ $densidad_promedio ?? 0 }},
            cantidad_provincias: {{ $cantidad_provincias ?? 'null' }},
            latestYear: {{ $latestYear ?? 2023 }},
            departamento: @json($departamento),
            provincia: @json($provincia),
            departamentos: @json($departamentos),
            provincias: @json($provincias)
        };
    </script>
</body>
</html>
