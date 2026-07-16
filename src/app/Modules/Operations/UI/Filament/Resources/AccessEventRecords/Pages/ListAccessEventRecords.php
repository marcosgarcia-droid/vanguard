<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Pages;

use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\AccessEventRecordResource;
use Filament\Resources\Pages\ListRecords;

class ListAccessEventRecords extends ListRecords
{
    protected static string $resource =
        AccessEventRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
