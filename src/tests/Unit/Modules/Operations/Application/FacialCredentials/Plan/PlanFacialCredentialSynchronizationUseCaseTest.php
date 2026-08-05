<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Application\FacialCredentials\Plan;

use App\Modules\Operations\Application\FacialCredentials\Plan\FacialCredentialSynchronizationPlanningReason;
use App\Modules\Operations\Application\FacialCredentials\Plan\PlanFacialCredentialSynchronizationCommand;
use App\Modules\Operations\Application\FacialCredentials\Plan\PlanFacialCredentialSynchronizationUseCase;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\DocumentedIntelbrasFacialCredentialCompatibilityCatalog;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasDeviceModel;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityCatalog;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityProfile;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityResolution;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialDeviceFamily;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFirmwareVersion;
use JsonException;
use Tests\TestCase;

final class PlanFacialCredentialSynchronizationUseCaseTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function test_it_builds_a_ready_plan_without_transport(): void
    {
        $result = $this
            ->useCase($this->compatibleProfile())
            ->execute($this->command());

        self::assertTrue($result->isReady());

        self::assertSame(
            FacialCredentialSynchronizationPlanningReason::Ready,
            $result->reason
        );

        self::assertNotNull($result->plan);
        self::assertSame(1, $result->plan->itemCount());

        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/D',
            (string) $result->planFingerprint()
        );

        $safeJson = json_encode(
            $result->toSafeArray(),
            JSON_THROW_ON_ERROR
        );

        self::assertStringNotContainsString(
            'visitor-synthetic-001',
            $safeJson
        );

        self::assertStringNotContainsString(
            'Pessoa Sintética',
            $safeJson
        );

        self::assertStringNotContainsString(
            str_repeat('a', 64),
            $safeJson
        );

        self::assertStringNotContainsString(
            'plan_fingerprint',
            $safeJson
        );
    }

    public function test_documented_catalog_remains_fail_closed(): void
    {
        $useCase = new PlanFacialCredentialSynchronizationUseCase(
            new DocumentedIntelbrasFacialCredentialCompatibilityCatalog
        );

        $result = $useCase->execute(
            $this->command(
                model: 'SS 3532 MF',
                firmware: '20260416',
            )
        );

        self::assertFalse($result->isReady());
        self::assertNull($result->plan);

        self::assertSame(
            FacialCredentialSynchronizationPlanningReason::UnverifiedCombination,
            $result->reason
        );
    }

    public function test_it_blocks_an_unsupported_replacement(): void
    {
        $profile = new IntelbrasFacialCredentialCompatibilityProfile(
            family: IntelbrasFacialCredentialDeviceFamily::SinglePerson,
            model: 'SYNTHETIC-DEVICE',
            firmware: '20991231',
            maxItems: 1,
            supportsReplacement: false,
            requiresDisplayName: true,
        );

        $result = $this
            ->useCase($profile)
            ->execute(
                $this->command(
                    operation: IntelbrasFacialCredentialOperation::Replace
                )
            );

        self::assertFalse($result->isReady());
        self::assertNull($result->plan);

        self::assertSame(
            FacialCredentialSynchronizationPlanningReason::UnsupportedOperation,
            $result->reason
        );
    }

    public function test_invalid_photo_metadata_fails_closed(): void
    {
        $result = $this
            ->useCase($this->compatibleProfile())
            ->execute(
                $this->command(
                    photoSizeBytes: 100_001
                )
            );

        self::assertFalse($result->isReady());
        self::assertNull($result->plan);

        self::assertSame(
            FacialCredentialSynchronizationPlanningReason::InvalidCredentialInput,
            $result->reason
        );
    }

    /**
     * @throws JsonException
     */
    public function test_equivalent_commands_have_the_same_fingerprint(): void
    {
        $useCase = $this->useCase(
            $this->compatibleProfile()
        );

        $first = $useCase->execute(
            $this->command()
        );

        $second = $useCase->execute(
            $this->command()
        );

        self::assertTrue($first->isReady());
        self::assertTrue($second->isReady());

        self::assertSame(
            $first->planFingerprint(),
            $second->planFingerprint()
        );
    }

    private function useCase(
        IntelbrasFacialCredentialCompatibilityProfile $profile
    ): PlanFacialCredentialSynchronizationUseCase {
        return new PlanFacialCredentialSynchronizationUseCase(
            new class($profile) implements IntelbrasFacialCredentialCompatibilityCatalog
            {
                public function __construct(
                    private readonly IntelbrasFacialCredentialCompatibilityProfile $profile
                ) {}

                public function resolve(
                    ?string $model,
                    ?string $firmware,
                ): IntelbrasFacialCredentialCompatibilityResolution {
                    return IntelbrasFacialCredentialCompatibilityResolution::compatible(
                        model: new IntelbrasDeviceModel(
                            $this->profile->model
                        ),
                        firmware: new IntelbrasFirmwareVersion(
                            $this->profile->firmware
                        ),
                        profile: $this->profile,
                    );
                }
            }
        );
    }

    private function compatibleProfile(): IntelbrasFacialCredentialCompatibilityProfile
    {
        return new IntelbrasFacialCredentialCompatibilityProfile(
            family: IntelbrasFacialCredentialDeviceFamily::SinglePerson,
            model: 'SYNTHETIC-DEVICE',
            firmware: '20991231',
            maxItems: 1,
            supportsReplacement: true,
            requiresDisplayName: true,
        );
    }

    private function command(
        string $model = 'SYNTHETIC-DEVICE',
        string $firmware = '20991231',
        IntelbrasFacialCredentialOperation $operation =
            IntelbrasFacialCredentialOperation::Register,
        int $photoSizeBytes = 50_000,
    ): PlanFacialCredentialSynchronizationCommand {
        return new PlanFacialCredentialSynchronizationCommand(
            deviceModel: $model,
            firmwareVersion: $firmware,
            operation: $operation,
            externalUserId: 'visitor-synthetic-001',
            displayName: 'Pessoa Sintética',
            photoSha256: str_repeat('a', 64),
            photoSizeBytes: $photoSizeBytes,
            photoWidth: 500,
            photoHeight: 800,
        );
    }
}
