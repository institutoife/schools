<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Colegios por Probabilidad de Reprobar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="{{ asset(config('brand.icon')) }}" type="image/png">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: 'rgb(38,186,165)',
                        secondary: 'rgb(55,95,122)',
                        accent: '#f9fafb'
                    }
                }
            }
        }
    </script>
    <script src="https://kit.fontawesome.com/your-code.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" crossorigin="anonymous" />
</head>
<body class="bg-accent min-h-screen font-sans">
    <div class="max-w-6xl mx-auto py-10 px-4">
        <div class="text-center mb-10">
            <h1 class="text-3xl md:text-4xl font-bold text-primary flex items-center justify-center gap-2">
                <i class="fas fa-exclamation-triangle"></i> Colegios por Probabilidad de Reprobar
            </h1>
            <p class="text-secondary text-lg mt-2 flex items-center justify-center gap-2">
                <i class="fas fa-filter"></i> Ordena y filtra colegios por riesgo académico
            </p>
        </div>

        <form method="GET" action="{{ url('schools/probabilidad') }}" class="mb-8 flex flex-col md:flex-row gap-4 items-end justify-center">
            <div>
                <label for="departamento" class="block text-secondary font-semibold mb-1">
                    <i class="fas fa-map-marker-alt"></i> Departamento
                </label>
                <select id="departamento" name="departamento" class="py-2 px-4 rounded-lg border border-primary/30 bg-primary/5 text-secondary w-full focus:ring-2 focus:ring-primary outline-none transition">
                    <option value="">Todo el país</option>
                    @foreach($departamentos as $dep)
                        <option value="{{ $dep }}" {{ request('departamento') == $dep ? 'selected' : '' }}>{{ $dep }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="orden" class="block text-secondary font-semibold mb-1">
                    <i class="fas fa-sort"></i> Ordenar
                </label>
                <select id="orden" name="orden" class="py-2 px-4 rounded-lg border border-primary/30 bg-primary/5 text-secondary w-full focus:ring-2 focus:ring-primary outline-none transition">
                    <option value="desc" {{ request('orden') == 'desc' ? 'selected' : '' }}>Mayor a menor</option>
                    <option value="asc" {{ request('orden') == 'asc' ? 'selected' : '' }}>Menor a mayor</option>
                </select>
            </div>
            <div>
                <button type="submit" class="bg-primary hover:bg-secondary text-white font-bold py-2 px-6 rounded-lg shadow transition flex items-center gap-2">
                    <i class="fas fa-search"></i> Filtrar
                </button>
            </div>
        </form>

        <div class="bg-white rounded-xl shadow p-6 mb-8 overflow-x-auto">
            <table class="min-w-full text-sm rounded-lg">
                <thead>
                    <tr class="bg-primary text-white">
                        <th class="py-3 px-2 font-semibold"><i class="fas fa-barcode"></i> Código</th>
                        <th class="py-3 px-2 font-semibold"><i class="fas fa-school"></i> Nombre</th>
                        <th class="py-3 px-2 font-semibold"><i class="fas fa-map-marker-alt"></i> Departamento</th>
                        <th class="py-3 px-2 font-semibold"><i class="fas fa-building"></i> Tipo</th>
                        <th class="py-3 px-2 font-semibold"><i class="fas fa-percentage"></i> Probabilidad de Reprobar</th>
                        <th class="py-3 px-2 font-semibold"><i class="fas fa-cogs"></i> Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($schools as $school)
                        @php
                            $totalMatricula = collect($school->estadisticas)->where('categoria', 'matricula')->sum('total');
                            $totalReprobados = collect($school->estadisticas)->where('categoria', 'reprobados')->sum('total');
                            $probabilidadReprobar = $totalMatricula > 0 ? round(($totalReprobados / $totalMatricula) * 100, 2) : 0;
                        @endphp
                        <tr class="border-b hover:bg-primary/10 transition">
                            <td class="py-2 px-2 font-bold">{{ $school->codigo_rue }}</td>
                            <td class="py-2 px-2">{{ $school->nombre }}</td>
                            <td class="py-2 px-2 text-secondary">
                                <i class="fas fa-map-marker-alt"></i>
                                {{ $school->ubicacion->departamento ?? 'N/A' }}
                            </td>
                            <td class="py-2 px-2">
                                <span class="inline-block px-3 py-1 rounded-full font-semibold text-white
                                    @if(strtolower($school->dependencia) == 'fiscal') bg-primary
                                    @elseif(strtolower($school->dependencia) == 'privado') bg-secondary
                                    @else bg-gray-500 @endif">
                                    <i class="fas fa-shield-alt"></i> {{ $school->dependencia }}
                                </span>
                            </td>
                            <td class="py-2 px-2 font-bold text-lg">
                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-primary/10 text-primary">
                                    <i class="fas fa-exclamation-triangle"></i> {{ $probabilidadReprobar }}%
                                </span>
                            </td>
                            <td class="py-2 px-2">
                                <a href="{{ route('schools.show', $school->id) }}"
                                    class="inline-flex items-center gap-1 px-3 py-1 rounded-lg border border-primary text-primary hover:bg-primary hover:text-white transition font-semibold">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="py-4 flex justify-center">
                {{ $schools->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
</body>
</html>
