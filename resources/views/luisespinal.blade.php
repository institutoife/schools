<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gráfico de Datos</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
    <style>
        /* Estilos generales */
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f7f9fa;
            color: rgb(55, 95, 122);
        }

        .container {
            max-width: 900px;
            margin: 40px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .header img {
            height: 50px;
        }

        .header h1 {
            font-size: 1.8rem;
            color: rgb(38, 186, 165);
            margin: 0;
        }

        .chart-container {
            padding: 20px;
            height: 500px; /* Aumentar la altura del gráfico */
        }

        footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.9rem;
            color: rgb(55, 95, 122);
        }

        footer a {
            color: rgb(38, 186, 165);
            text-decoration: none;
        }

        footer a:hover {
            text-decoration: underline;
        }

        /* Media query para pantallas pequeñas */
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.4rem; /* Reducir el tamaño del título */
            }

            .chart-container {
                padding: 10px; /* Reducir el padding del gráfico */
                height: 400px; /* Ajustar la altura del gráfico */
            }

            footer {
                font-size: 0.8rem; /* Reducir el tamaño del texto del pie de página */
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Encabezado con logotipo -->
        <div class="header">
            <img src="{{ asset(config('brand.logo')) }}" alt="Logo de {{ config('brand.name') }}">
            <h1>Predicción para 2025</h1>
           
        </div>

        <!-- Contenedor del gráfico -->
        <div class="chart-container">
            <h3>Colegio Luis Espinal</h3>
            <canvas id="graficoDatos"></canvas>
        </div>z

        <!-- Pie de página -->
        <footer>
            <p>Desarrollado por <a href="#">David Flores</a> | © 2025</p>
        </footer>
    </div>

    <script>
        // Datos para el gráfico
        const labels = ['2023', '2024', '2025']; // Años
        const mujeresReprobadas = [45, 38, 42]; // Mujeres reprobadas
        const hombresReprobados = [57, 50, 60]; // Hombres reprobados

        // Configuración del gráfico
        const data = {
            labels: labels,
            datasets: [
                {
                    label: 'Mujeres',
                    data: mujeresReprobadas,
                    backgroundColor: function(context) {
                        return context.dataIndex === 2 ? 'rgba(38,186,165,0.9)' : 'rgb(38,186,165)';
                    },
                    borderColor: function(context) {
                        return context.dataIndex === 2 ? 'rgba(38,186,165,1)' : 'rgb(38,186,165)';
                    },
                    borderWidth: function(context) {
                        return context.dataIndex === 2 ? 2 : 1;
                    }
                },
                {
                    label: 'Hombres',
                    data: hombresReprobados,
                    backgroundColor: function(context) {
                        return context.dataIndex === 2 ? 'rgba(55,95,122,0.9)' : 'rgb(55,95,122)';
                    },
                    borderColor: function(context) {
                        return context.dataIndex === 2 ? 'rgba(55,95,122,1)' : 'rgb(55,95,122)';
                    },
                    borderWidth: function(context) {
                        return context.dataIndex === 2 ? 2 : 1;
                    }
                }
            ]
        };

        // Opciones del gráfico
        const options = {
            responsive: true,
            maintainAspectRatio: false, // Permitir que el gráfico se ajuste al contenedor
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: ${context.raw}`;
                        }
                    }
                },
                datalabels: {
                    display: true,
                    color: '#fff', // Color del texto
                    font: {
                        weight: 'bold'
                    },
                    align: 'center', // Centrar el texto dentro de la barra
                    anchor: 'center', // Centrar el texto verticalmente
                    formatter: function(value, context) {
                        // Mostrar la palabra (Hombres/Mujeres) y la cantidad
                        return `      ${value}\n${context.dataset.label}`;
                    },
                    padding: {
                        top: 10, // Espaciado entre las líneas
                        bottom: 10
                    }
                }
            },
            scales: {
                x: {
                    stacked: true,
                    title: {
                        display: true,
                        text: 'Años'
                    }
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Cantidad de Reprobados'
                    }
                }
            }
        };

        // Crear el gráfico
        const ctx = document.getElementById('graficoDatos').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: data,
            options: options,
            plugins: [ChartDataLabels] // Activa el plugin de etiquetas
        });
    </script>
</body>
</html>
