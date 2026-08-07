<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Application\FacialCredentials\Create;

use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationCommand;
use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationReason;
use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationRepository;
use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationResult;
use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationUseCase;
use App\Modules\Operations\Application\FacialCredentials\Create\FacialCredentialSynchronizationContext;
use App\Modules\Operations\Application\FacialCredentials\Create\FacialCredentialSynchronizationPreparation;
use App\Modules\Operations\Application\FacialCredentials\Plan\FacialCredentialSynchronizationPlanningReason;
use App\Modules\Operations\Application\FacialCredentials\Plan\PlanFacialCredentialSynchronizationUseCase;
use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSubjectType;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\DocumentedIntelbrasFacialCredentialCompatibilityCatalog;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasDeviceModel;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityCatalog;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityProfile;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityResolution;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialDeviceFamily;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFirmwareVersion;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CreateFacialCredentialSynchronizationUseCaseTest extends TestCase
{
    public function test_preparation_block_is_propagated_without_persistence(): void
    {
        $repository = new InMemoryCreateFacialCredentialSynchronizationRepository(
            preparation: FacialCredentialSynchronizationPreparation::blocked(
                CreateFacialCredentialSynchronizationReason::VisitorInactive
            ),
            persistenceResult: CreateFacialCredentialSynchronizationResult::blocked(
                CreateFacialCredentialSynchronizationReason::ContextChanged
            ),
        );

        $result = $this
            ->useCase(
                $repository,
                $this->compatibleCatalog()
            )
            ->execute($this->command());

        self::assertSame(
            CreateFacialCredentialSynchronizationReason::VisitorInactive,
            $result->reason
        );

        self::assertSame(
            0,
            $repository->persistCalls
        );
    }

    public function test_ready_context_creates_a_deterministic_intention(): void
    {
        $repository = new InMemoryCreateFacialCredentialSynchronizationRepository(
            preparation: FacialCredentialSynchronizationPreparation::ready(
                $this->context()
            ),
            persistenceResult: CreateFacialCredentialSynchronizationResult::created(
                synchronizationId: (string) Str::uuid(),
                version: 1,
            ),
        );

        $useCase = $this->useCase(
            $repository,
            $this->compatibleCatalog()
        );

        $first = $useCase->execute(
            $this->command()
        );

        $second = $useCase->execute(
            $this->command()
        );

        self::assertTrue($first->wasCreated());
        self::assertTrue($second->wasCreated());

        self::assertSame(
            2,
            $repository->persistCalls
        );

        self::assertCount(
            2,
            $repository->captured
        );

        self::assertSame(
            $repository->captured[0]['plan_fingerprint'],
            $repository->captured[1]['plan_fingerprint']
        );

        self::assertSame(
            $repository->captured[0]['context_fingerprint'],
            $repository->captured[1]['context_fingerprint']
        );

        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/D',
            $repository->captured[0]['plan_fingerprint']
        );

        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/D',
            $repository->captured[0]['context_fingerprint']
        );
    }

    public function test_documented_catalog_blocks_before_persistence(): void
    {
        $repository = new InMemoryCreateFacialCredentialSynchronizationRepository(
            preparation: FacialCredentialSynchronizationPreparation::ready(
                $this->context(
                    deviceModel: 'SS 3532 MF',
                    firmwareVersion: '20260416',
                )
            ),
            persistenceResult: CreateFacialCredentialSynchronizationResult::created(
                synchronizationId: (string) Str::uuid(),
                version: 1,
            ),
        );

        $result = $this
            ->useCase(
                $repository,
                new DocumentedIntelbrasFacialCredentialCompatibilityCatalog
            )
            ->execute($this->command());

        self::assertSame(
            CreateFacialCredentialSynchronizationReason::PlanningBlocked,
            $result->reason
        );

        self::assertSame(
            FacialCredentialSynchronizationPlanningReason::UnverifiedCombination,
            $result->planningReason
        );

        self::assertSame(
            0,
            $repository->persistCalls
        );
    }

    public function test_repository_reuse_is_preserved(): void
    {
        $repository = new InMemoryCreateFacialCredentialSynchronizationRepository(
            preparation: FacialCredentialSynchronizationPreparation::ready(
                $this->context()
            ),
            persistenceResult: CreateFacialCredentialSynchronizationResult::reused(
                synchronizationId: (string) Str::uuid(),
                version: 3,
            ),
        );

        $result = $this
            ->useCase(
                $repository,
                $this->compatibleCatalog()
            )
            ->execute($this->command());

        self::assertTrue($result->wasReused());
        self::assertSame(3, $result->version);
    }

    public function test_safe_result_exposes_no_hashes_or_person_context(): void
    {
        $result =
            CreateFacialCredentialSynchronizationResult::created(
                synchronizationId: (string) Str::uuid(),
                version: 1,
            );

        $serialized = json_encode(
            $result->toSafeArray(),
            JSON_THROW_ON_ERROR
        );

        self::assertStringNotContainsString(
            'fingerprint',
            $serialized
        );

        self::assertStringNotContainsString(
            'sha256',
            $serialized
        );

        self::assertStringNotContainsString(
            'VISITANTE SINTÉTICO',
            $serialized
        );
    }

    private function useCase(
        CreateFacialCredentialSynchronizationRepository $repository,
        IntelbrasFacialCredentialCompatibilityCatalog $catalog,
    ): CreateFacialCredentialSynchronizationUseCase {
        return new CreateFacialCredentialSynchronizationUseCase(
            repository: $repository,
            planner: new PlanFacialCredentialSynchronizationUseCase(
                $catalog
            ),
        );
    }

    private function command(): CreateFacialCredentialSynchronizationCommand
    {
        return new CreateFacialCredentialSynchronizationCommand(
            subjectType: FacialCredentialSubjectType::Visitor,
            subjectId: 'visitor-synthetic-001',
            accessDeviceId: 'device-synthetic-001',
            operation: IntelbrasFacialCredentialOperation::Register,
        );
    }

    private function context(
        string $deviceModel = 'SYNTHETIC-DEVICE',
        string $firmwareVersion = '20991231',
    ): FacialCredentialSynchronizationContext {
        return new FacialCredentialSynchronizationContext(
            tenantId: 'tenant-synthetic-001',
            organizationId: 'organization-synthetic-001',
            subjectType: FacialCredentialSubjectType::Visitor,
            subjectId: 'visitor-synthetic-001',
            subjectDisplayName: 'VISITANTE SINTÉTICO',
            externalUserId: 'visitor-synthetic-001',
            facialPhotoId: 'photo-synthetic-001',
            facialPhotoDerivativeId: 'derivative-synthetic-001',
            accessDeviceId: 'device-synthetic-001',
            configurationSnapshotId: 'snapshot-synthetic-001',
            deviceModel: $deviceModel,
            firmwareVersion: $firmwareVersion,
            derivativeSha256: str_repeat('a', 64),
            derivativeSizeBytes: 50_000,
            derivativeWidth: 500,
            derivativeHeight: 800,
            derivativeMimeType: 'image/jpeg',
        );
    }

    private function compatibleCatalog(): IntelbrasFacialCredentialCompatibilityCatalog
    {
        return new class implements IntelbrasFacialCredentialCompatibilityCatalog
        {
            public function resolve(
                ?string $model,
                ?string $firmware,
            ): IntelbrasFacialCredentialCompatibilityResolution {
                $profile =
                    new IntelbrasFacialCredentialCompatibilityProfile(
                        family: IntelbrasFacialCredentialDeviceFamily::SinglePerson,
                        model: 'SYNTHETIC-DEVICE',
                        firmware: '20991231',
                        maxItems: 1,
                        supportsReplacement: true,
                        requiresDisplayName: true,
                    );

                return IntelbrasFacialCredentialCompatibilityResolution::compatible(
                    model: new IntelbrasDeviceModel(
                        $profile->model
                    ),
                    firmware: new IntelbrasFirmwareVersion(
                        $profile->firmware
                    ),
                    profile: $profile,
                );
            }
        };
    }
}

final class InMemoryCreateFacialCredentialSynchronizationRepository implements CreateFacialCredentialSynchronizationRepository
{
    public int $persistCalls = 0;

    /**
     * @var list<array{
     *     plan_fingerprint: string,
     *     context_fingerprint: string
     * }>
     */
    public array $captured = [];

    public function __construct(
        private readonly FacialCredentialSynchronizationPreparation $preparation,
        private readonly CreateFacialCredentialSynchronizationResult $persistenceResult,
    ) {}

    public function prepare(
        CreateFacialCredentialSynchronizationCommand $command
    ): FacialCredentialSynchronizationPreparation {
        return $this->preparation;
    }

    public function persist(
        FacialCredentialSynchronizationContext $context,
        IntelbrasFacialCredentialOperation $operation,
        string $planFingerprint,
        string $contextFingerprint,
    ): CreateFacialCredentialSynchronizationResult {
        $this->persistCalls++;

        $this->captured[] = [
            'plan_fingerprint' => $planFingerprint,
            'context_fingerprint' => $contextFingerprint,
        ];

        return $this->persistenceResult;
    }
}
