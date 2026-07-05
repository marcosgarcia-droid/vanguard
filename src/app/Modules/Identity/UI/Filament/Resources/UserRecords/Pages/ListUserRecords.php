<?php

namespace App\Modules\Identity\UI\Filament\Resources\UserRecords\Pages;

use App\Modules\Identity\UI\Filament\Resources\UserRecords\UserRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUserRecords extends ListRecords
{
    protected static string $resource = UserRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Novo usuário')
                ->modalHeading('Novo usuário')
                ->modalSubmitActionLabel('Salvar')
                ->successNotificationTitle('Usuário cadastrado'),
        ];
    }
}
