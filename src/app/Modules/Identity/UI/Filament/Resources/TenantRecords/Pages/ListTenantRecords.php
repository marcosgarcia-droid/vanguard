<?php

namespace App\Modules\Identity\UI\Filament\Resources\TenantRecords\Pages;

use App\Modules\Identity\UI\Filament\Resources\TenantRecords\TenantRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTenantRecords extends ListRecords
{
    protected static string $resource = TenantRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Novo tenant')
                ->modalHeading('Novo tenant')
                ->modalSubmitActionLabel('Salvar')
                ->successNotificationTitle('Tenant cadastrado'),
        ];
    }
}
