<?php

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Pages;

use App\Modules\Identity\UI\Filament\Actions\SelectCurrentTenantFirstAction;
use App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\EmployeeRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListEmployeeRecords extends ListRecords
{
    protected static string $resource = EmployeeRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SelectCurrentTenantFirstAction::make(),
            CreateAction::make()
                ->label('Novo funcionário')
                ->modalHeading('Novo funcionário')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalSubmitActionLabel('Salvar')
                ->successNotificationTitle('Funcionário cadastrado'),
        ];
    }
}
