<?php

namespace App\Modules\Identity\UI\Filament\Resources\ActivityLogs\Pages;

use AlizHarb\ActivityLog\Resources\ActivityLogs\Pages\ViewActivityLog;
use App\Modules\Identity\UI\Filament\Resources\ActivityLogs\VanguardActivityLogResource;

class ViewVanguardActivityLog extends ViewActivityLog
{
    protected static string $resource = VanguardActivityLogResource::class;

    public function getTitle(): string
    {
        return 'Visualizar log de atividade';
    }
}
