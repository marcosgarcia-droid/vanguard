<?php

namespace Tests\Unit\Support\ActivityLog;

use App\Support\ActivityLog\VanguardActivityLogPresenter;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AccessEventManualAssociationActivityPresenterTest extends TestCase
{
    public function test_it_presents_a_complete_manual_association_in_portuguese(): void
    {
        $activity = new Activity;

        $activity->event =
            'access_event_manually_associated';

        $activity->properties = collect([
            'status' => 'success',
            'visitor_name' => 'VISITANTE SINTÉTICO A4 ATIVO',
            'visit_reference' => '16/07/2026 16:39 - VALIDAÇÃO A4 AUTORIZADA - AUTORIZADA',
            'resulting_status' => 'processed',
            'reason' => 'Identidade conferida manualmente.',
            'message' => 'Evento associado manualmente ao visitante e à visita.',
            'duplicate' => false,

            /*
             * Identificadores internos não devem ser apresentados.
             */
            'association_id' => 'association-internal-id',
            'visitor_id' => 'visitor-internal-id',
            'visit_id' => 'visit-internal-id',
            'result_code' => 'manual_association_completed',
        ]);

        $this->assertSame(
            'Associação manual',
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
                    'label' => 'Visitante',
                    'value' => 'VISITANTE SINTÉTICO A4 ATIVO',
                ],
                [
                    'label' => 'Visita',
                    'value' => '16/07/2026 16:39 - VALIDAÇÃO A4 AUTORIZADA - AUTORIZADA',
                ],
                [
                    'label' => 'Situação final',
                    'value' => 'Processado',
                ],
                [
                    'label' => 'Justificativa',
                    'value' => 'Identidade conferida manualmente.',
                ],
                [
                    'label' => 'Mensagem',
                    'value' => 'Evento associado manualmente ao visitante e à visita.',
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
            'association-internal-id',
            'visitor-internal-id',
            'visit-internal-id',
            'manual_association_completed',
        ] as $internalValue) {
            $this->assertStringNotContainsString(
                $internalValue,
                $serialized
            );
        }

        $this->assertSame(
            'heroicon-m-link',
            config(
                'filament-activity-log.events.access_event_manually_associated.icon'
            )
        );

        $this->assertSame(
            'info',
            config(
                'filament-activity-log.events.access_event_manually_associated.color'
            )
        );
    }

    public function test_it_identifies_a_visitor_only_association(): void
    {
        $activity = new Activity;

        $activity->event =
            'access_event_manually_associated';

        $activity->properties = collect([
            'status' => 'success',
            'visitor_name' => 'VISITANTE SEM VISITA',
            'visit_reference' => null,
            'resulting_status' => 'pending_association',
            'reason' => 'Visitante identificado.',
            'message' => 'O evento permanece aguardando uma visita.',
            'duplicate' => false,
        ]);

        $details =
            VanguardActivityLogPresenter::operationDetails(
                $activity
            );

        $this->assertContains(
            [
                'label' => 'Visita',
                'value' => 'Não associada',
            ],
            $details
        );

        $this->assertContains(
            [
                'label' => 'Situação final',
                'value' => 'Aguardando associação',
            ],
            $details
        );
    }

    public function test_it_presents_a_failed_manual_association_without_internal_ids(): void
    {
        $activity = new Activity;

        $activity->event =
            'access_event_manually_associated';

        $activity->properties = collect([
            'status' => 'failed',
            'message' => 'O operador não possui autorização.',
            'association_id' => 'internal-failed-association',
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
                    'value' => 'O operador não possui autorização.',
                ],
            ],
            $details
        );

        $this->assertStringNotContainsString(
            'internal-failed-association',
            json_encode($details) ?: ''
        );
    }
}
