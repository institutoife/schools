<x-filament::section heading="Importar colegios desde JSON">
    {{ $this->form }}

    <x-filament::button wire:click="submit" type="button" class="mt-4">
        Procesar importación
    </x-filament::button>
</x-filament::section>
