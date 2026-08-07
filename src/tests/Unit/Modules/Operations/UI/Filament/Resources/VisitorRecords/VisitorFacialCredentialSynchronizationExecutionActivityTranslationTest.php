<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use App\Support\ActivityLog\VanguardActivityLogPresenter;
use Tests\TestCase;

final class VisitorFacialCredentialSynchronizationExecutionActivityTranslationTest extends TestCase
{
    public function test_it_translates_all_execution_events(): void
    {
        $events = [
            'visitor_facial_credential_synchronization_execution_succeeded' => 'Sincronização facial simulada concluída',

            'visitor_facial_credential_synchronization_execution_requires_attention' => 'Sincronização facial simulada requer atenção',

            'visitor_facial_credential_synchronization_execution_blocked' => 'Execução da sincronização facial bloqueada',

            'visitor_facial_credential_synchronization_execution_failed' => 'Falha na execução da sincronização facial',

            'visitor_facial_credential_synchronization_execution_not_performed' => 'Sincronização facial não executada',
        ];

        foreach ($events as $event => $expected) {
            $translationKey =
                'filament-activity-log::activity.event.'
                .$event;

            self::assertSame(
                $expected,
                trans($translationKey)
            );

            self::assertSame(
                $expected,
                VanguardActivityLogPresenter::eventLabel(
                    $event
                )
            );

            self::assertNotSame(
                $translationKey,
                trans($translationKey)
            );
        }
    }
}
