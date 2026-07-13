<?php

namespace App\Support\ActivityLog;

use AlizHarb\ActivityLog\Actions\ActivityLogTimelineTableAction;

class VanguardActivityLogTimelineAction extends ActivityLogTimelineTableAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Histórico')
            ->modalHeading('Histórico de alterações')
            ->tooltip('Ver histórico de alterações')
            ->visible(fn (): bool => auth()->user()?->hasRole(config('filament-shield.super_admin.name', 'super_admin')) ?? false);
    }
}
