<?php

namespace Tests\Unit\Support\ActivityLog;

use App\Support\ActivityLog\VanguardActivityLogPresenter;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AccessEventManualReviewActivityPresenterTest extends TestCase
{
    public function test_it_presents_a_successful_manual_review_in_portuguese(): void
    {
        $activity = new Activity;

        $activity->event =
            'access_event_manual_review_recorded';

        $activity->properties = collect([
            'status' => 'success',
            'disposition' => 'ready_for_reprocessing',

            'decision_reason_message' => 'O visitante não possui foto facial local para uma futura operação de entrada.',

            'notes' => 'A foto facial foi cadastrada e validada pela portaria.',

            'duplicate' => false,

            'message' => 'A correção foi registrada e o evento está pronto para reprocessamento manual.',

            /*
             * Valores internos que não podem aparecer.
             */
            'review_id' => 'internal-review-id',
            'decision_id' => 'internal-decision-id',
            'decision_reason_code' => 'visitor_photo_missing',
        ]);

        $this->assertSame(
            'Análise manual',
            VanguardActivityLogPresenter::eventLabel(
                $activity->event
            )
        );

        $details =
            VanguardActivityLogPresenter::operationDetails(
                $activity
            );

        $this->assertSame(
            [
                [
                    'label' => 'Resultado',
                    'value' => 'Concluído',
                ],
                [
                    'label' => 'Situação da análise',
                    'value' => 'Pronto para reprocessamento',
                ],
                [
                    'label' => 'Motivo da revisão',
                    'value' => 'O visitante não possui foto facial local para uma futura operação de entrada.',
                ],
                [
                    'label' => 'Observações',
                    'value' => 'A foto facial foi cadastrada e validada pela portaria.',
                ],
                [
                    'label' => 'Sem novo registro',
                    'value' => 'Não',
                ],
                [
                    'label' => 'Mensagem',
                    'value' => 'A correção foi registrada e o evento está pronto para reprocessamento manual.',
                ],
            ],
            $details
        );

        $serialized = json_encode(
            $details,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        ) ?: '';

        foreach ([
            'internal-review-id',
            'internal-decision-id',
            'visitor_photo_missing',
        ] as $internalValue) {
            $this->assertStringNotContainsString(
                $internalValue,
                $serialized
            );
        }

        $this->assertSame(
            'heroicon-m-clipboard-document-check',
            config(
                'filament-activity-log.events.access_event_manual_review_recorded.icon'
            )
        );

        $this->assertSame(
            'warning',
            config(
                'filament-activity-log.events.access_event_manual_review_recorded.color'
            )
        );
    }

    public function test_it_presents_a_failed_manual_review_without_internal_ids(): void
    {
        $activity = new Activity;

        $activity->event =
            'access_event_manual_review_recorded';

        $activity->properties = collect([
            'status' => 'failed',
            'message' => 'O operador não possui autorização para analisar este evento.',
            'review_id' => 'internal-failed-review-id',
        ]);

        $details =
            VanguardActivityLogPresenter::operationDetails(
                $activity
            );

        $this->assertSame(
            [
                [
                    'label' => 'Resultado',
                    'value' => 'Falha',
                ],
                [
                    'label' => 'Mensagem',
                    'value' => 'O operador não possui autorização para analisar este evento.',
                ],
            ],
            $details
        );

        $this->assertStringNotContainsString(
            'internal-failed-review-id',
            json_encode($details) ?: ''
        );
    }
}
