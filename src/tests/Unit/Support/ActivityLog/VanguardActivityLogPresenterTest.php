<?php

namespace Tests\Unit\Support\ActivityLog;

use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
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
}
