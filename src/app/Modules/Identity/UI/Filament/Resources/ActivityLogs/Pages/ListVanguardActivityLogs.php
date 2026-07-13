<?php

namespace App\Modules\Identity\UI\Filament\Resources\ActivityLogs\Pages;

use AlizHarb\ActivityLog\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Modules\Identity\UI\Filament\Resources\ActivityLogs\VanguardActivityLogResource;

class ListVanguardActivityLogs extends ListActivityLogs
{
    protected static string $resource = VanguardActivityLogResource::class;

    public function getTitle(): string
    {
        return 'Logs de atividade';
    }
}
