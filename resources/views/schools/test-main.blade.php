<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Principal - Densidad</title>
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
            <h1><i class="fas fa-chart-area"></i> Test Dashboard de Densidad</h1>
            <p>Página de prueba para diagnosticar el problema del heatmap</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid" id="stats-grid">
            <!-- Stats will be populated by JavaScript -->
        </div>

        <!-- Heatmap Section -->
        <div class="heatmap-container">
            <h3><i class="fas fa-fire"></i> Mapa de Calor por Departamentos</h3>
            <div class="heatmap-grid" id="heatmap-grid">
                <!-- Heatmap items will be populated by JavaScript -->
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-section">
            <!-- Bar Chart -->
            <div class="chart-container">
                <h3><i class="fas fa-chart-bar"></i> Distribución de Colegios por Departamento</h3>
                <div class="chart-wrapper">
                    <canvas id="bar-chart"></canvas>
                </div>
            </div>

            <!-- Pie Chart -->
            <div class="chart-container">
                <h3><i class="fas fa-chart-pie"></i> Proporción de Colegios por Departamento</h3>
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
    </div>

    <!-- Leaflet JavaScript -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="{{ asset('js/vistas/schools/densidad.js') }}"></script>

    <script>
        // Simulate PHP data exactly as it would be in the main page
        window.phpData = {
            results: [
                { departamento: 'La Paz', cantidad_colegios: 1250, total_estudiantes: 450000, densidad: 85 },
                { departamento: 'Santa Cruz', cantidad_colegios: 980, total_estudiantes: 380000, densidad: 72 },
                { departamento: 'Cochabamba', cantidad_colegios: 750, total_estudiantes: 280000, densidad: 65 },
                { departamento: 'Potosí', cantidad_colegios: 420, total_estudiantes: 150000, densidad: 45 },
                { departamento: 'Oruro', cantidad_colegios: 380, total_estudiantes: 120000, densidad: 40 }
            ],
            total_colegios: 3780,
            total_estudiantes: 1380000,
            densidad_promedio: 61.4,
            cantidad_provincias: null,
            latestYear: 2023,
            departamento: null,
            provincia: null,
            departamentos: ['La Paz', 'Santa Cruz', 'Cochabamba', 'Potosí', 'Oruro'],
            provincias: []
        };
        
        console.log('=== TEST MAIN PAGE START ===');
        console.log('window.phpData set:', window.phpData);
    </script>
</body>
</html>
