<?php

namespace Tests\Unit\Support\ActivityLog;

use App\Support\ActivityLog\VanguardActivityLogPresenter;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AccessEventFlowReprocessActivityPresenterTest extends TestCase
{
    public function test_it_presents_access_event_reprocessing_details_in_portuguese(): void
    {
        $activity = new Activity;

        $activity->event =
            'access_event_flow_reprocessed';

        $activity->properties = collect([
            'status' => 'success',
            'processing_status' => 'processed',
            'decision' => 'check_in_candidate',
            'decision_version' => 1,
            'execution_status' => 'blocked',
            'execution_reason_code' => 'automatic_execution_disabled',
            'all_duplicates' => true,
            'message' => 'Fluxo reprocessado sem novas alterações.',
        ]);

        $this->assertSame(
            'Reprocessamento do fluxo',
            VanguardActivityLogPresenter::eventLabel(
                $activity->event
            )
        );

        $details =
            VanguardActivityLogPresenter::operationDetails(
                $activity
            );

        $this->assertContains(
            [
                'label' => 'Resultado',
                'value' => 'Concluído',
            ],
            $details
        );

        $this->assertContains(
            [
                'label' => 'Processamento',
                'value' => 'Processado',
            ],
            $details
        );

        $this->assertContains(
            [
                'label' => 'Decisão',
                'value' => 'Candidato a entrada',
            ],
            $details
        );

        $this->assertContains(
            [
                'label' => 'Tentativa',
                'value' => 'Bloqueada',
            ],
            $details
        );

        $this->assertContains(
            [
                'label' => 'Sem novas alterações',
                'value' => 'Sim',
            ],
            $details
        );
    }

    public function test_it_presents_a_consumed_manual_review_release_without_internal_ids(): void
    {
        $activity = new Activity;

        $activity->event =
            'access_event_flow_reprocessed';

        $activity->properties = collect([
            'status' => 'success',
            'processing_status' => 'processed',
            'decision' => 'manual_review',
            'decision_version' => 1,
            'execution_status' => 'skipped',

            'execution_reason_code' => 'decision_not_executable',

            'all_duplicates' => false,

            'manual_review_release_used' => true,

            'manual_review_release_consumed' => true,

            'manual_review_id' => 'internal-review-id',

            'manual_review_consumption_id' => 'internal-consumption-id',

            'message' => 'A liberação da análise manual foi consumida.',
        ]);

        $details =
            VanguardActivityLogPresenter::operationDetails(
                $activity
            );

        foreach ([
            [
                'label' => 'Liberação da análise manual',

                'value' => 'Consumida',
            ],
            [
                'label' => 'Uso da liberação',
                'value' => 'Único',
            ],
            [
                'label' => 'Nova análise para outra tentativa',

                'value' => 'Obrigatória',
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
            'internal-review-id',
            'internal-consumption-id',
        ] as $internalValue) {
            $this->assertStringNotContainsString(
                $internalValue,
                $serialized
            );
        }
    }

    public function test_it_explains_when_a_failed_reprocessing_consumed_the_release(): void
    {
        $activity = new Activity;

        $activity->event =
            'access_event_flow_reprocessed';

        $activity->properties = collect([
            'status' => 'failed',

            'manual_review_release_consumed' => true,

            'manual_review_id' => 'failed-internal-review-id',

            'manual_review_consumption_id' => 'failed-internal-consumption-id',

            'message' => 'Não foi possível reprocessar o fluxo. A liberação manual foi consumida.',
        ]);

        $details =
            VanguardActivityLogPresenter::operationDetails(
                $activity
            );

        $this->assertContains(
            [
                'label' => 'Liberação da análise manual',

                'value' => 'Consumida',
            ],
            $details
        );

        $this->assertContains(
            [
                'label' => 'Nova análise para outra tentativa',

                'value' => 'Obrigatória',
            ],
            $details
        );

        $serialized = json_encode(
            $details,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        ) ?: '';

        foreach ([
            'failed-internal-review-id',
            'failed-internal-consumption-id',
        ] as $internalValue) {
            $this->assertStringNotContainsString(
                $internalValue,
                $serialized
            );
        }
    }

    public function test_it_presents_a_failed_reprocessing(): void
    {
        $activity = new Activity;

        $activity->event =
            'access_event_flow_reprocessed';

        $activity->properties = collect([
            'status' => 'failed',
            'message' => 'Não foi possível processar o evento.',
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
                'value' => 'Não foi possível processar o evento.',
            ],
            $details
        );
    }
}
