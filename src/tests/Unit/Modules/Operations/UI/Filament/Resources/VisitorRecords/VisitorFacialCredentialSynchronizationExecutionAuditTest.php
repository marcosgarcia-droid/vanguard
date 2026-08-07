<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationReason;
use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationResult;
use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationAttemptStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialCredentialSynchronizationRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Actions\VisitorFacialCredentialSynchronizationExecutionAudit;
use Tests\TestCase;

final class VisitorFacialCredentialSynchronizationExecutionAuditTest extends TestCase
{
    public function test_it_maps_execution_results_to_friendly_events(): void
    {
        $cases = [
            [
                FacialCredentialSynchronizationAttemptStatus::Succeeded,
                'visitor_facial_credential_synchronization_execution_succeeded',
                'Sincronização facial simulada concluída',
            ],
            [
                FacialCredentialSynchronizationAttemptStatus::RequiresAttention,
                'visitor_facial_credential_synchronization_execution_requires_attention',
                'Sincronização facial simulada requer atenção',
            ],
            [
                FacialCredentialSynchronizationAttemptStatus::Blocked,
                'visitor_facial_credential_synchronization_execution_blocked',
                'Execução da sincronização facial bloqueada',
            ],
            [
                FacialCredentialSynchronizationAttemptStatus::Failed,
                'visitor_facial_credential_synchronization_execution_failed',
                'Falha na execução da sincronização facial',
            ],
        ];

        foreach (
            $cases as [
                $status,
                $event,
                $description,
            ]
        ) {
            $result =
                ExecuteFacialCredentialSynchronizationResult::executed(
                    synchronizationId: '10000000-0000-4000-8000-000000000001',
                    attemptNumber: 1,
                    status: $status,
                    provider: 'simulator',
                    scenario: 'succeeded',
                    failureCode: null,
                );

            self::assertSame(
                $event,
                VisitorFacialCredentialSynchronizationExecutionAudit::event(
                    $result
                )
            );

            self::assertSame(
                $description,
                VisitorFacialCredentialSynchronizationExecutionAudit::description(
                    $result
                )
            );
        }

        $notPerformed =
            ExecuteFacialCredentialSynchronizationResult::withoutAttempt(
                reason: ExecuteFacialCredentialSynchronizationReason::SynchronizationNotExecutable,

                synchronizationId: '10000000-0000-4000-8000-000000000001',
            );

        self::assertSame(
            'visitor_facial_credential_synchronization_execution_not_performed',
            VisitorFacialCredentialSynchronizationExecutionAudit::event(
                $notPerformed
            )
        );

        self::assertSame(
            'Sincronização facial não executada',
            VisitorFacialCredentialSynchronizationExecutionAudit::description(
                $notPerformed
            )
        );
    }

    public function test_it_builds_only_safe_friendly_properties(): void
    {
        $device = (new AccessDeviceRecord)->forceFill([
            'id' => '20000000-0000-4000-8000-000000000001',
            'code' => 'FAC-A5G3',
            'name' => 'Leitor facial sintético A5G.3',
        ]);

        $synchronization =
            (new FacialCredentialSynchronizationRecord)->forceFill([
                'id' => '10000000-0000-4000-8000-000000000001',
                'access_device_id' => $device->getKey(),
                'operation' => 'register',
                'version' => 3,
                'plan_fingerprint' => str_repeat('a', 64),
                'context_fingerprint' => str_repeat('b', 64),
            ]);

        $synchronization->setRelation(
            'accessDevice',
            $device
        );

        $result =
            ExecuteFacialCredentialSynchronizationResult::reused(
                synchronizationId: (string) $synchronization->getKey(),

                attemptNumber: 2,

                status: FacialCredentialSynchronizationAttemptStatus::RequiresAttention,

                provider: 'simulator',
                scenario: 'duplicate_photo',
                failureCode: 'duplicate_photo',
            );

        $properties =
            VisitorFacialCredentialSynchronizationExecutionAudit::properties(
                synchronization: $synchronization,
                result: $result,
            );

        self::assertSame(
            'Resultado existente reutilizado',
            $properties['resultado']
        );

        self::assertSame(
            'Requer atenção',
            $properties['situação']
        );

        self::assertSame(
            2,
            $properties['tentativa']
        );

        self::assertSame(
            'Sim',
            $properties['reutilizado']
        );

        self::assertSame(
            'Simulador local',
            $properties['origem']
        );

        self::assertSame(
            'Foto duplicada sintética',
            $properties['cenário']
        );

        self::assertSame(
            'Cadastro',
            $properties['operação']
        );

        self::assertSame(
            3,
            $properties['versão']
        );

        $encoded = strtolower(
            json_encode(
                $properties,
                JSON_THROW_ON_ERROR
            )
        );

        foreach ([
            'plan_fingerprint',
            'context_fingerprint',
            'failure_code',
            'source_sha256',
            'sha256',
            'photo_path',
            'credential_username',
            'credential_password',
            'raw_payload',
            'base64',
            '10000000-0000-4000-8000-000000000001',
        ] as $prohibited) {
            self::assertStringNotContainsString(
                strtolower($prohibited),
                $encoded
            );
        }
    }

    public function test_internal_failure_properties_are_generic(): void
    {
        $properties =
            VisitorFacialCredentialSynchronizationExecutionAudit::failureProperties(
                null
            );

        self::assertSame(
            [
                'resultado' => 'Falha interna antes da conclusão da tentativa',
            ],
            $properties
        );
    }
}
