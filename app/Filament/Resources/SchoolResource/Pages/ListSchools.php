<?php

namespace App\Filament\Resources\SchoolResource\Pages;

use App\Filament\Resources\SchoolResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSchools extends ListRecords
{
    protected static string $resource = SchoolResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('importarMinisterio')
                ->label('Actualizar desde Ministerio')
                ->url(\App\Filament\Pages\MinistrySchoolImport::getUrl()),
            Actions\CreateAction::make(),
        ];
    }
}
