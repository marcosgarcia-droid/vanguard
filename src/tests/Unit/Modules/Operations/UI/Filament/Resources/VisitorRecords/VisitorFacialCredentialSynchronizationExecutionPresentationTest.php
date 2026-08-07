<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationResult;
use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationAttemptStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialCredentialSynchronizationRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Actions\VisitorFacialCredentialSynchronizationExecutionPresentation;
use Tests\TestCase;

final class VisitorFacialCredentialSynchronizationExecutionPresentationTest extends TestCase
{
    public function test_it_presents_a_successful_simulation(): void
    {
        $result = ExecuteFacialCredentialSynchronizationResult::executed(
            synchronizationId: '10000000-0000-4000-8000-000000000001',
            attemptNumber: 1,
            status: FacialCredentialSynchronizationAttemptStatus::Succeeded,
            provider: 'simulator',
            scenario: 'succeeded',
            failureCode: null,
        );

        $presentation =
            VisitorFacialCredentialSynchronizationExecutionPresentation::fromResult(
                $result
            );

        self::assertSame(
            'success',
            $presentation['level']
        );

        self::assertSame(
            'Sincronização facial simulada',
            $presentation['title']
        );

        self::assertStringContainsString(
            'Tentativa 1',
            $presentation['body']
        );

        self::assertStringContainsString(
            'Simulador local',
            $presentation['body']
        );

        self::assertStringNotContainsString(
            '10000000-0000-4000-8000-000000000001',
            $presentation['body']
        );
    }

    public function test_it_presents_attention_and_reused_results(): void
    {
        $result = ExecuteFacialCredentialSynchronizationResult::reused(
            synchronizationId: '10000000-0000-4000-8000-000000000002',
            attemptNumber: 2,
            status: FacialCredentialSynchronizationAttemptStatus::RequiresAttention,
            provider: 'simulator',
            scenario: 'duplicate_photo',
            failureCode: 'duplicate_photo',
        );

        $presentation =
            VisitorFacialCredentialSynchronizationExecutionPresentation::fromResult(
                $result
            );

        self::assertSame(
            'warning',
            $presentation['level']
        );

        self::assertStringContainsString(
            'resultado já existente foi reutilizado',
            $presentation['body']
        );

        self::assertStringContainsString(
            'Foto duplicada sintética',
            $presentation['body']
        );

        self::assertStringNotContainsString(
            'duplicate_photo',
            $presentation['body']
        );
    }

    public function test_it_builds_a_friendly_synchronization_label(): void
    {
        $device = (new AccessDeviceRecord)->forceFill([
            'id' => '20000000-0000-4000-8000-000000000001',
            'name' => 'Leitor facial sintético A5G.3',
            'code' => 'FAC-A5G3',
        ]);

        $synchronization =
            (new FacialCredentialSynchronizationRecord)->forceFill([
                'id' => '10000000-0000-4000-8000-000000000003',
                'access_device_id' => $device->getKey(),
                'operation' => 'register',
                'version' => 3,
            ]);

        $synchronization->setRelation(
            'accessDevice',
            $device
        );

        self::assertSame(
            'Leitor facial sintético A5G.3 — Cadastrar face — versão 3',
            VisitorFacialCredentialSynchronizationExecutionPresentation::synchronizationLabel(
                $synchronization
            )
        );
    }
}
