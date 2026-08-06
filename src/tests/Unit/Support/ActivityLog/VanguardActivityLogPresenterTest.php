<?php

namespace Tests\Unit\Support\ActivityLog;

use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Support\ActivityLog\VanguardActivityLogPresenter;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class VanguardActivityLogPresenterTest extends TestCase
{
    public function test_it_presents_device_configuration_read_details_in_portuguese(): void
    {
        $device = new AccessDeviceRecord;

        $device->forceFill([
            'id' => '7cefa752-6728-4388-9db1-dcdc05a78816',
            'code' => 'FAC-TESTE-01',
            'name' => 'Facial teste observador',
        ]);

        $activity = new Activity;

        $activity->forceFill([
            'event' => 'configuration_read',
            'subject_type' => AccessDeviceRecord::class,
            'subject_id' => $device->id,
            'properties' => [
                'snapshot_id' => 'snapshot-interno-nao-exibido',
                'status' => 'failed',
                'source' => 'manual',
                'duration_ms' => 12,
                'message' => 'O endereço IP do dispositivo é inválido.',
                'warnings' => [],
            ],
        ]);

        $activity->setRelation(
            'subject',
            $device
        );

        $this->assertSame(
            'Leitura de configurações',
            VanguardActivityLogPresenter::eventLabel(
                $activity->event
            )
        );

        $this->assertSame(
            'Dispositivo de acesso — FAC-TESTE-01 - Facial teste observador',
            VanguardActivityLogPresenter::subjectLabel(
                $activity
            )
        );

        $this->assertSame(
            [
                [
                    'label' => 'Resultado',
                    'value' => 'Falha na leitura',
                ],
                [
                    'label' => 'Origem',
                    'value' => 'Consulta manual',
                ],
                [
                    'label' => 'Duração',
                    'value' => '12 ms',
                ],
                [
                    'label' => 'Mensagem',
                    'value' => 'O endereço IP do dispositivo é inválido.',
                ],
            ],
            VanguardActivityLogPresenter::operationDetails(
                $activity
            )
        );

        $serialized = json_encode(
            VanguardActivityLogPresenter::operationDetails(
                $activity
            )
        ) ?: '';

        $this->assertStringNotContainsString(
            'snapshot-interno-nao-exibido',
            $serialized
        );
    }

    public function test_it_presents_facial_photo_derivative_reprocessing_with_a_friendly_visitor_identity(): void
    {
        $internalId =
            '78bfe1de-8cda-48e5-a59e-381df037a28b';

        $visitor = new VisitorRecord;

        $visitor->forceFill([
            'id' => $internalId,
            'full_name' => 'VISITANTE SINTÉTICO A5F.5 20260805-082535',
            'preferred_name' => 'A5F.5 VISUAL',
        ]);

        $success = new Activity;

        $success->forceFill([
            'event' => 'visitor_facial_photo_derivative_reprocessing_requested',
            'subject_type' => VisitorRecord::class,
            'subject_id' => $internalId,
        ]);

        $success->setRelation(
            'subject',
            $visitor
        );

        $this->assertSame(
            'Reprocessamento da preparação facial',
            VanguardActivityLogPresenter::eventLabel(
                $success->event
            )
        );

        $this->assertSame(
            'Visitante — A5F.5 VISUAL',
            VanguardActivityLogPresenter::subjectLabel(
                $success
            )
        );

        $this->assertStringNotContainsString(
            $internalId,
            VanguardActivityLogPresenter::subjectLabel(
                $success
            )
        );

        $this->assertStringNotContainsString(
            'VisitorRecord',
            VanguardActivityLogPresenter::subjectLabel(
                $success
            )
        );

        $failure = new Activity;

        $failure->forceFill([
            'event' => 'visitor_facial_photo_derivative_reprocessing_failed',
            'subject_type' => VisitorRecord::class,
            'subject_id' => $internalId,
        ]);

        $failure->setRelation(
            'subject',
            $visitor
        );

        $this->assertSame(
            'Falha no reprocessamento da preparação facial',
            VanguardActivityLogPresenter::eventLabel(
                $failure->event
            )
        );

        $missingSubject = new Activity;

        $missingSubject->forceFill([
            'event' => 'visitor_facial_photo_derivative_reprocessing_requested',
            'subject_type' => VisitorRecord::class,
            'subject_id' => $internalId,
        ]);

        $missingSubject->setRelation(
            'subject',
            null
        );

        $this->assertSame(
            'Visitante',
            VanguardActivityLogPresenter::subjectLabel(
                $missingSubject
            )
        );

        $this->assertSame(
            'Visitante',
            VanguardActivityLogPresenter::modelLabel(
                VisitorRecord::class
            )
        );
    }

    public function test_it_does_not_show_operational_details_for_regular_updates(): void
    {
        $activity = new Activity;

        $activity->forceFill([
            'event' => 'updated',
            'properties' => [
                'message' => 'Este conteúdo não pertence ao evento.',
            ],
        ]);

        $this->assertSame(
            [],
            VanguardActivityLogPresenter::operationDetails(
                $activity
            )
        );
    }

    public function test_timeline_uses_the_operational_details_presenter(): void
    {
        $view = file_get_contents(
            resource_path(
                'views/vendor/filament-activity-log/timeline.blade.php'
            )
        );

        $this->assertIsString($view);

        $this->assertStringContainsString(
            'VanguardActivityLogPresenter::operationDetails',
            $view
        );

        $this->assertStringContainsString(
            'Detalhes da operação',
            $view
        );

        $this->assertSame(
            'heroicon-m-arrow-path',
            config(
                'filament-activity-log.events.configuration_read.icon'
            )
        );
    }

    public function test_facial_photo_reprocessing_uses_specific_timeline_icons_and_history_is_an_icon_button(): void
    {
        $this->assertSame(
            'heroicon-m-arrow-path',
            config(
                'filament-activity-log.events.'
                .'visitor_facial_photo_derivative_'
                .'reprocessing_requested.icon'
            )
        );

        $this->assertSame(
            'info',
            config(
                'filament-activity-log.events.'
                .'visitor_facial_photo_derivative_'
                .'reprocessing_requested.color'
            )
        );

        $this->assertSame(
            'heroicon-m-exclamation-triangle',
            config(
                'filament-activity-log.events.'
                .'visitor_facial_photo_derivative_'
                .'reprocessing_failed.icon'
            )
        );

        $this->assertSame(
            'danger',
            config(
                'filament-activity-log.events.'
                .'visitor_facial_photo_derivative_'
                .'reprocessing_failed.color'
            )
        );

        $action = file_get_contents(
            base_path(
                'app/Support/ActivityLog/'
                .'VanguardActivityLogTimelineAction.php'
            )
        );

        $this->assertIsString(
            $action
        );

        $this->assertStringContainsString(
            "->icon('heroicon-o-clock')",
            $action
        );

        $this->assertStringContainsString(
            '->iconButton()',
            $action
        );

        $this->assertStringContainsString(
            "->tooltip('Ver histórico de alterações')",
            $action
        );

        $configuration = file_get_contents(
            base_path(
                'config/filament-activity-log.php'
            )
        );

        $this->assertIsString(
            $configuration
        );

        $this->assertStringContainsString(
            'heroicon-m-arrow-path',
            $configuration
        );

        $this->assertStringContainsString(
            'heroicon-m-exclamation-triangle',
            $configuration
        );
    }

    public function test_it_uses_the_activity_translation_before_the_headline_fallback(): void
    {
        $previousLocale = app()->getLocale();

        try {
            app()->setLocale('pt_BR');

            self::assertSame(
                'Intenção de sincronização facial criada',
                VanguardActivityLogPresenter::eventLabel(
                    'visitor_facial_credential_synchronization_created'
                )
            );

            self::assertSame(
                'Intenção de sincronização facial reutilizada',
                VanguardActivityLogPresenter::eventLabel(
                    'visitor_facial_credential_synchronization_reused'
                )
            );

            self::assertSame(
                'Preparação da sincronização facial bloqueada',
                VanguardActivityLogPresenter::eventLabel(
                    'visitor_facial_credential_synchronization_blocked'
                )
            );

            self::assertSame(
                'Falha ao preparar sincronização facial',
                VanguardActivityLogPresenter::eventLabel(
                    'visitor_facial_credential_synchronization_failed'
                )
            );

            self::assertSame(
                'Evento Ainda Sem Traducao',
                VanguardActivityLogPresenter::eventLabel(
                    'evento_ainda_sem_traducao'
                )
            );
        } finally {
            app()->setLocale(
                $previousLocale
            );
        }
    }
}
