<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\UI\Filament\Resources\EmployeeRecords;

use App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Actions\EmployeeFacialCredentialSynchronizationCreationPresentation;
use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationReason;
use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationResult;
use App\Modules\Operations\Application\FacialCredentials\Plan\FacialCredentialSynchronizationPlanningReason;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EmployeeFacialCredentialSynchronizationCreationPresentationTest extends TestCase
{
    public function test_it_presents_a_created_intention_without_exposing_its_id(): void
    {
        $synchronizationId =
            (string) Str::uuid();

        $result =
            CreateFacialCredentialSynchronizationResult::created(
                synchronizationId: $synchronizationId,
                version: 2,
            );

        $title =
            EmployeeFacialCredentialSynchronizationCreationPresentation::title(
                $result
            );

        $message =
            EmployeeFacialCredentialSynchronizationCreationPresentation::message(
                result: $result,
                operation: IntelbrasFacialCredentialOperation::Register,
                deviceLabel: 'FAC-SYN-001 - Leitor sintético',
            );

        self::assertSame(
            'Intenção de sincronização criada',
            $title
        );

        self::assertStringContainsString(
            'cadastro',
            mb_strtolower($message)
        );

        self::assertStringContainsString(
            'versão 2',
            $message
        );

        self::assertStringContainsString(
            'Nenhuma comunicação com o equipamento foi realizada.',
            $message
        );

        self::assertStringNotContainsString(
            $synchronizationId,
            $message
        );
    }

    public function test_it_presents_an_equivalent_reused_intention(): void
    {
        $result =
            CreateFacialCredentialSynchronizationResult::reused(
                synchronizationId: (string) Str::uuid(),
                version: 1,
            );

        self::assertSame(
            'Intenção existente reutilizada',
            EmployeeFacialCredentialSynchronizationCreationPresentation::title(
                $result
            )
        );

        $message =
            EmployeeFacialCredentialSynchronizationCreationPresentation::message(
                result: $result,
                operation: IntelbrasFacialCredentialOperation::Replace,
                deviceLabel: 'FAC-SYN-002 - Leitor sintético',
            );

        self::assertStringContainsString(
            'intenção equivalente',
            mb_strtolower($message)
        );

        self::assertStringContainsString(
            'substituição',
            mb_strtolower($message)
        );

        self::assertStringContainsString(
            'Nenhuma comunicação com o equipamento foi realizada.',
            $message
        );
    }

    public function test_it_translates_a_fail_closed_catalog_block(): void
    {
        $result =
            CreateFacialCredentialSynchronizationResult::blocked(
                reason: CreateFacialCredentialSynchronizationReason::PlanningBlocked,
                planningReason: FacialCredentialSynchronizationPlanningReason::UnverifiedCombination,
            );

        self::assertSame(
            'Não foi possível preparar a sincronização',
            EmployeeFacialCredentialSynchronizationCreationPresentation::title(
                $result
            )
        );

        self::assertSame(
            'A combinação de modelo e firmware ainda não possui compatibilidade comprovada.',
            EmployeeFacialCredentialSynchronizationCreationPresentation::message(
                result: $result,
                operation: IntelbrasFacialCredentialOperation::Register,
                deviceLabel: 'FAC-SYN-003',
            )
        );
    }

    public function test_it_sanitizes_the_device_label(): void
    {
        $result =
            CreateFacialCredentialSynchronizationResult::created(
                synchronizationId: (string) Str::uuid(),
                version: 1,
            );

        $message =
            EmployeeFacialCredentialSynchronizationCreationPresentation::message(
                result: $result,
                operation: IntelbrasFacialCredentialOperation::Register,
                deviceLabel: "FAC-SYN-004\nLeitor sintético",
            );

        self::assertStringNotContainsString(
            "\n",
            $message
        );
    }
}
