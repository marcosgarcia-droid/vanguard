<?php

namespace App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Pages;

use App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Actions\SyncOrganizationCnpjAction;
use App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\OrganizationRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOrganizationRecord extends EditRecord
{
    protected static string $resource = OrganizationRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SyncOrganizationCnpjAction::make(),
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
