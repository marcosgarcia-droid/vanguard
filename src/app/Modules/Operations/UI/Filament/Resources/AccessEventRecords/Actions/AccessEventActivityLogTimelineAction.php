<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions;

use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalExecutionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Support\ActivityLog\VanguardActivityLogTimelineAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

final class AccessEventActivityLogTimelineAction extends VanguardActivityLogTimelineAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Histórico')
            ->tooltip(
                'Ver histórico do evento de acesso'
            )
            ->modalHeading(
                'Histórico do evento de acesso'
            );
    }

    protected function getActivities(
        ?Model $record
    ): Collection {
        $activities =
            parent::getActivities($record);

        if (
            ! $record instanceof AccessEventRecord
        ) {
            return $activities;
        }

        return $activities
            ->merge(
                $this->getOperationalActivities(
                    $record
                )
            )
            ->unique('id')
            ->sortByDesc('created_at')
            ->take(100)
            ->values();
    }

    private function getOperationalActivities(
        AccessEventRecord $event
    ): Collection {
        $decisionIds = $event
            ->operationalDecisions()
            ->pluck('id')
            ->map(
                fn (
                    mixed $id
                ): string => (string) $id
            )
            ->all();

        $executionIds = $event
            ->operationalExecutions()
            ->pluck('id')
            ->map(
                fn (
                    mixed $id
                ): string => (string) $id
            )
            ->all();

        if (
            $decisionIds === []
            && $executionIds === []
        ) {
            return collect();
        }

        return Activity::query()
            ->with([
                'causer',
                'subject',
            ])
            ->where(
                function ($query) use (
                    $decisionIds,
                    $executionIds
                ): void {
                    if ($decisionIds !== []) {
                        $query->where(
                            function (
                                $decisionQuery
                            ) use (
                                $decisionIds
                            ): void {
                                $decisionQuery
                                    ->where(
                                        'subject_type',
                                        AccessEventOperationalDecisionRecord::class
                                    )
                                    ->whereIn(
                                        'subject_id',
                                        $decisionIds
                                    );
                            }
                        );
                    }

                    if ($executionIds !== []) {
                        $method =
                            $decisionIds === []
                                ? 'where'
                                : 'orWhere';

                        $query->{$method}(
                            function (
                                $executionQuery
                            ) use (
                                $executionIds
                            ): void {
                                $executionQuery
                                    ->where(
                                        'subject_type',
                                        AccessEventOperationalExecutionRecord::class
                                    )
                                    ->whereIn(
                                        'subject_id',
                                        $executionIds
                                    );
                            }
                        );
                    }
                }
            )
            ->latest()
            ->limit(100)
            ->get();
    }
}
