<x-filament-panels::page>
    <p>@if($workerRunning) Ejecutor del servidor activo: el proceso puede continuar con esta sección cerrada. @else El ejecutor del servidor está detenido. Mantén esta sección abierta para procesar o inicia el ejecutor. @endif</p>
    <p>El Excel aporta únicamente el código RUE y el departamento para agrupar los archivos. Los datos administrativos, ubicación, infraestructura y estadísticas hasta 2025 se leen de cada ficha oficial. Los valores no publicados se conservan en la base.</p>
    <form wire:submit="start" class="space-y-4">
        {{ $this->form }}
        <x-filament::button type="submit" wire:loading.attr="disabled">Cargar Excel e iniciar actualización</x-filament::button>
    </form>
    @if($importId)
        <section class="space-y-4" @if(($progress['estado'] ?? '') === 'procesando') wire:poll.2s="advance" @endif>
            <p><strong>Estado:</strong> {{ $progress['estado'] ?? '' }}. <strong>Último RUE:</strong> {{ $progress['ultimo_rue'] ?? '—' }}</p>
            <p>El progreso queda guardado. Si cierras esta sección, puedes volver a abrir la importación para continuar. También puede procesarse con el ejecutor del servidor.</p>
            <div class="flex flex-wrap gap-3">
                @if(($progress['estado'] ?? '') === 'procesando')
                    <x-filament::button wire:click="control('pausar')" color="gray">Pausar</x-filament::button>
                @elseif(($progress['estado'] ?? '') === 'pausado')
                    <x-filament::button wire:click="control('reanudar')">Reanudar</x-filament::button>
                @elseif(($progress['estado'] ?? '') === 'con_errores')
                    <x-filament::button wire:click="control('reintentar')">Reintentar fichas fallidas</x-filament::button>
                @endif
                @if(in_array($progress['estado'] ?? '', ['completo','con_errores']))
                    <x-filament::button wire:click="download" color="gray">Descargar 9 JSON y reporte</x-filament::button>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead><tr><th class="p-2">Departamento</th><th class="p-2">Fichas leídas</th><th class="p-2">Estado</th><th class="p-2">Resultado de importación</th></tr></thead>
                    <tbody>
                    @foreach($progress['departamentos'] ?? [] as $department)
                        <tr class="border-t"><td class="p-2">{{ $department['nombre'] }}</td><td class="p-2">{{ $department['leidos'] }} / {{ $department['total'] }}</td><td class="p-2">{{ $department['estado'] }}</td>
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
            <div><x-filament::button color="gray" wire:click="selectImport('{{ $item['id'] }}')">{{ $item['fecha'] }} — {{ $item['estado'] }}</x-filament::button></div>
        @empty<p>Aún no hay importaciones del Ministerio.</p>@endforelse
    </section>
</x-filament-panels::page>
