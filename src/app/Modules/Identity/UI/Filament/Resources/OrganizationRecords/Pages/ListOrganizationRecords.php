<?php

namespace App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Pages;

use App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\OrganizationRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrganizationRecords extends ListRecords
{
    protected static string $resource = OrganizationRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
