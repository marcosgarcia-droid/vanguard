<?php

namespace App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Pages;

use App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Actions\SyncOrganizationCnpjAction;
use App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\OrganizationRecordResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOrganizationRecord extends ViewRecord
{
    protected static string $resource = OrganizationRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SyncOrganizationCnpjAction::make(),
            EditAction::make(),
        ];
    }
}
