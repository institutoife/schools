<?php

namespace App\Filament\Pages;

use App\Services\MinistrySchoolSync;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class MinistrySchoolImport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = 'Actualizar desde Ministerio';
    protected static ?string $title = 'Actualizar colegios desde el Ministerio — Gestión 2025';
    protected static string $view = 'filament.pages.ministry-school-import';
    public ?array $data = [];
    public ?string $importId = null;
    public array $progress = [];
    public array $history = [];
    public bool $workerRunning = false;

    public function mount(): void
    {
        $this->form->fill();
        $this->refreshProgress();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            FileUpload::make('archivo')->label('Excel con RUE en A y departamento en C')
                ->helperText('Los demás datos se consultarán en las fichas oficiales. El ejecutor del servidor permite continuar con esta sección cerrada.')
                ->disk('local')->directory('importaciones/excel')->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->maxSize(20480)->required(),
        ])->statePath('data');
    }

    public function start(): void
    {
        $data = $this->form->getState();
        try {
            $this->importId = app(MinistrySchoolSync::class)->create(Storage::disk('local')->path($data['archivo']));
            $this->refreshProgress();
            Notification::make()->title('Procesamiento iniciado')->body('Se consultará cada RUE y se importará automáticamente al terminar su departamento.')->success()->send();
        } catch (\Throwable $e) { $this->notifyError($e); }
    }

    public function selectImport(string $id): void
    {
        try {
            app(MinistrySchoolSync::class)->state($id);
            $this->importId = $id;
            $this->refreshProgress();
        } catch (\Throwable $e) { $this->notifyError($e); }
    }

    public function advance(): void
    {
        if (!$this->importId) return;
        try {
            set_time_limit(120);
            app(MinistrySchoolSync::class)->tick($this->importId);
            $this->refreshProgress();
        } catch (\Throwable $e) { $this->notifyError($e); }
    }

    public function control(string $action): void
    {
        if (!$this->importId || !in_array($action, ['pausar', 'reanudar', 'reintentar'], true)) return;
        try { app(MinistrySchoolSync::class)->change($this->importId, $action); $this->refreshProgress(); }
        catch (\Throwable $e) { $this->notifyError($e); }
    }

    public function download()
    {
        try {
            if ($this->importId) return response()->download(app(MinistrySchoolSync::class)->archive($this->importId));
        } catch (\Throwable $e) { $this->notifyError($e); }
    }

    private function refreshProgress(): void
    {
        $service = app(MinistrySchoolSync::class);
        $this->workerRunning = $service->workerRunning();
        $this->history = $service->history();
        $this->progress = [];
        if (!$this->importId) return;
        $state = $service->state($this->importId);
        $this->progress = ['estado' => $state['estado'], 'ultimo_rue' => $state['ultimo_rue'], 'departamentos' => []];
        foreach ($state['departamentos'] as $slug => $department) {
            $read = count(array_filter($department['colegios'], fn ($s) => $s['estado'] === 'leido'));
            $errors = [];
            foreach ($department['colegios'] as $rue => $school) if ($school['error']) $errors[] = ['rue' => $rue, 'mensaje' => $school['error']];
            $this->progress['departamentos'][$slug] = ['nombre' => $department['nombre'], 'estado' => $department['estado'], 'total' => count($department['colegios']), 'leidos' => $read, 'errores' => $errors, 'error' => $department['error'], 'resultado' => $department['resultado']];
        }
    }

    private function notifyError(\Throwable $e): void
    {
        Notification::make()->title('No se pudo completar la acción')->body($e->getMessage())->danger()->send();
    }
}
