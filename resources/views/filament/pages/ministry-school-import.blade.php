<x-filament-panels::page>
    <section class="space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h2 class="text-lg font-semibold">Importar JSON ya descargados</h2>
        <p>Para la actualización anual: procesa el Excel una vez en local, descarga el ZIP y súbelo aquí en el servidor. También puedes subir los JSON de uno o varios departamentos. Esta opción reutiliza las fichas descargadas, sin volver a consultar al Ministerio.</p>
        <p>Los archivos deben ser los generados por esta sección para la gestión 2025. Se actualiza por RUE y se conservan los datos no incluidos. Puedes importar el mismo archivo de nuevo sin duplicar colegios. Los departamentos no cargados quedan sin modificar.</p>
        <form wire:submit="startJson" class="space-y-4">
            {{ $this->jsonForm }}
            <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray" wire:loading.attr="disabled">Importar JSON a la base de datos</x-filament::button>
        </form>
        <p class="text-sm text-gray-500">Si la descarga local quedó incompleta, se importarán solamente los colegios presentes en los JSON. Revisa el reporte del ZIP y resuelve las fichas fallidas en local para completar la actualización.</p>
    </section>
    <h2 class="text-lg font-semibold">Obtener datos nuevos desde Excel y Ministerio</h2>
    <p>@if($workerRunning) Ejecutor del servidor activo: el proceso puede continuar con esta sección cerrada. @else El ejecutor del servidor está detenido. Mantén esta sección abierta para procesar o inicia el ejecutor. @endif</p>
    <p>El Excel aporta únicamente el código RUE y el departamento para agrupar los archivos. Los datos administrativos, ubicación, infraestructura y estadísticas hasta 2025 se leen de cada ficha oficial. Los valores no publicados se conservan en la base.</p>
    <form wire:submit="start" class="space-y-4">
        {{ $this->form }}
        <x-filament::button type="submit" wire:loading.attr="disabled">Cargar Excel e iniciar actualización</x-filament::button>
    </form>
    @if($importId)
        <section class="space-y-4" @if(($progress['estado'] ?? '') === 'procesando') wire:poll.2s="advance" @endif>
            <p><strong>Origen:</strong> {{ ($progress['origen'] ?? '') === 'json' ? 'JSON cargados · sin consultas al Ministerio' : 'Excel y fichas oficiales del Ministerio' }}</p>
            <p><strong>Estado:</strong> {{ $progress['estado'] ?? '' }}. <strong>Último RUE:</strong> {{ $progress['ultimo_rue'] ?? '—' }}</p>
            <p>El progreso queda guardado. Si cierras esta sección, puedes volver a abrir la importación para continuar. También puede procesarse con el ejecutor del servidor.</p>
            <div class="flex flex-wrap gap-3">
                @if(($progress['estado'] ?? '') === 'procesando')
                    <x-filament::button wire:click="control('pausar')" color="gray">Pausar</x-filament::button>
                @elseif(($progress['estado'] ?? '') === 'pausado')
                    <x-filament::button wire:click="control('reanudar')">Reanudar</x-filament::button>
                @elseif(($progress['estado'] ?? '') === 'con_errores')
                    <x-filament::button wire:click="control('reintentar')">{{ ($progress['origen'] ?? '') === 'json' ? 'Reintentar importación pendiente' : 'Reintentar fichas fallidas' }}</x-filament::button>
                @endif
                @if(in_array($progress['estado'] ?? '', ['completo','con_errores']))
                    <x-filament::button wire:click="download" color="gray">Descargar 9 JSON y reporte</x-filament::button>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead><tr><th class="p-2">Departamento</th><th class="p-2">{{ ($progress['origen'] ?? '') === 'json' ? 'Colegios importados' : 'Fichas leídas' }}</th><th class="p-2">Estado</th><th class="p-2">Resultado de importación</th></tr></thead>
                    <tbody>
                    @foreach($progress['departamentos'] ?? [] as $department)
                        <tr class="border-t"><td class="p-2">{{ $department['nombre'] }}</td><td class="p-2">{{ ($progress['origen'] ?? '') === 'json' ? $department['importados'] : $department['leidos'] }} / {{ $department['total'] }}</td><td class="p-2">{{ $department['estado'] === 'omitido' ? 'No cargado' : $department['estado'] }}</td>
                            <td class="p-2">@if($department['resultado']) Nuevos: {{ $department['resultado']['nuevos'] }}. Actualizados: {{ $department['resultado']['actualizados'] }}. Sin cambios: {{ $department['resultado']['sin_cambios'] }}.@else Pendiente @endif</td></tr>
                        @if($department['error'] || $department['errores'])
                            <tr><td colspan="4" class="p-2"><details><summary>Ver incidencias ({{ count($department['errores']) }})</summary>
                                @if($department['error'])<p>{{ $department['error'] }}</p>@endif
                                @foreach($department['errores'] as $error)<p>RUE {{ $error['rue'] }}: {{ $error['mensaje'] }}</p>@endforeach
                            </details></td></tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
    <section class="space-y-2">
        <h2 class="text-lg font-semibold">Importaciones guardadas</h2>
        @forelse($history as $item)
            <div><x-filament::button color="gray" wire:click="selectImport('{{ $item['id'] }}')">{{ $item['fecha'] }} — {{ $item['origen'] === 'json' ? 'JSON' : 'Excel' }} — {{ $item['estado'] }}</x-filament::button></div>
        @empty<p>Aún no hay importaciones del Ministerio.</p>@endforelse
    </section>
</x-filament-panels::page>
