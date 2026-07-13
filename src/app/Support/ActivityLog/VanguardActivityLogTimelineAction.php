<?php

namespace App\Support\ActivityLog;

use AlizHarb\ActivityLog\Actions\ActivityLogTimelineTableAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

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

    protected function getActivities(?Model $record): Collection
    {
        return parent::getActivities($record)
            ->merge($this->getChildActivities($record))
            ->unique('id')
            ->sortByDesc('created_at')
            ->take(100)
            ->values();
    }

    private function getChildActivities(?Model $record): Collection
    {
        if ($record === null) {
            return collect();
        }

        return Activity::query()
            ->with(['causer', 'subject'])
            ->where('properties->vanguard_parent_type', $record::class)
            ->where('properties->vanguard_parent_id', (string) $record->getKey())
            ->latest()
            ->limit(100)
            ->get();
    }
}
