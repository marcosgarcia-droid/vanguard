<?php

namespace Tests\Unit\Support\ActivityLog;

use App\Support\ActivityLog\VanguardActivityLogPresenter;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AccessEventManualAssociationFlowContinuationActivityPresenterTest extends TestCase
{
    public function test_it_presents_the_manual_association_continuation_in_portuguese(): void
    {
        $activity = new Activity;

        $activity->event =
            'access_event_manual_association_flow_continued';

        $activity->properties = collect([
            'status' => 'success',
            'association_id' => 'internal-association-id',
            'processing_status' => 'processed',
            'processing_result_code' => 'manual_association_completed',
            'decision_id' => 'internal-decision-id',
            'decision' => 'check_in_candidate',
            'decision_version' => 1,
            'execution_id' => 'internal-execution-id',
            'execution_status' => 'blocked',
            'execution_reason_code' => 'automatic_execution_disabled',
            'all_duplicates' => false,
            'message' => 'Processamento: Processado. Decisão: Candidato a entrada. Tentativa: Bloqueada.',
        ]);

        $this->assertSame(
            'Continuação após associação manual',
            VanguardActivityLogPresenter::eventLabel(
                $activity->event
            )
        );

        $details =
            VanguardActivityLogPresenter::operationDetails(
                $activity
            );

        foreach ([
            [
                'label' => 'Resultado',
                'value' => 'Concluído',
            ],
            [
                'label' => 'Processamento',
                'value' => 'Processado',
            ],
            [
                'label' => 'Decisão',
                'value' => 'Candidato a entrada',
            ],
            [
                'label' => 'Tentativa',
                'value' => 'Bloqueada',
            ],
            [
                'label' => 'Sem novas alterações',
                'value' => 'Não',
            ],
        ] as $expected) {
            $this->assertContains(
                $expected,
                $details
            );
        }

        $serialized = json_encode(
            $details,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        ) ?: '';

        foreach ([
            'internal-association-id',
            'internal-decision-id',
            'internal-execution-id',
            'manual_association_completed',
        ] as $internalValue) {
            $this->assertStringNotContainsString(
                $internalValue,
                $serialized
            );
        }

        $this->assertSame(
            'heroicon-m-play-circle',
            config(
                'filament-activity-log.events.access_event_manual_association_flow_continued.icon'
            )
        );

        $this->assertSame(
            'warning',
            config(
                'filament-activity-log.events.access_event_manual_association_flow_continued.color'
            )
        );
    }

    public function test_it_presents_a_failed_continuation(): void
    {
        $activity = new Activity;

        $activity->event =
            'access_event_manual_association_flow_continued';

        $activity->properties = collect([
            'status' => 'failed',
            'message' => 'O contexto atual não corresponde à associação manual.',
        ]);

        $details =
            VanguardActivityLogPresenter::operationDetails(
                $activity
            );

        $this->assertContains(
            [
                'label' => 'Resultado',
                'value' => 'Falha',
            ],
            $details
        );

        $this->assertContains(
            [
                'label' => 'Mensagem',
                'value' => 'O contexto atual não corresponde à associação manual.',
            ],
            $details
        );
    }
}
