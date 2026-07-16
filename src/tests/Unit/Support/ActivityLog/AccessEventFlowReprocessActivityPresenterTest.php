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
