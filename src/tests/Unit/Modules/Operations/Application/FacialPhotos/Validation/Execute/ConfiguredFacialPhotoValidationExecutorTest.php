<?php

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Validation\Execute;

use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ConfiguredFacialPhotoValidationExecutor;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationCommand;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationException;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationRepository;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationResult;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\FacialPhotoValidationPersistenceData;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\FacialPhotoValidationTarget;
use App\Modules\Operations\Application\FacialPhotos\Validation\FacialPhotoValidator;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorProvider;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorResolutionException;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorResolver;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorSelection;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatusTransitionPolicy;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationResult;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfiguredFacialPhotoValidationExecutorTest extends TestCase
{
    public function test_it_blocks_before_resolving_or_loading_the_photo_when_disabled(): void
    {
        $validation = $this->approvedValidation();

        $resolver =
            new ExecutorFacialPhotoValidatorResolverStub(
                validator: new ExecutorFacialPhotoValidatorStub(
                    $validation
                )
            );

        $repository =
            new ExecutorFacialPhotoValidationRepositoryStub(
                target: $this->target(),
                result: $this->executionResult(
                    $validation
                ),
            );

        $executor = $this->executor(
            enabled: false,
            provider: null,
            scenario: null,
            resolver: $resolver,
            repository: $repository,
        );

        $this->assertSame(0, $resolver->calls);
        $this->assertSame(0, $repository->findCalls);
        $this->assertSame(0, $repository->persistCalls);

        try {
            $executor->execute(
                new ExecuteFacialPhotoValidationCommand(
                    photoId: 'photo-1'
                )
            );

            $this->fail(
                'A validação desativada deveria bloquear a execução.'
            );
        } catch (
            ExecuteFacialPhotoValidationException $exception
        ) {
            $this->assertSame(
                'A validação facial está desativada neste ambiente.',
                $exception->getMessage()
            );
        }

        $this->assertSame(0, $resolver->calls);
        $this->assertSame(0, $repository->findCalls);
        $this->assertSame(0, $repository->persistCalls);
    }

    public function test_it_requires_a_provider_before_resolving_or_loading_the_photo(): void
    {
        $validation = $this->approvedValidation();

        $resolver =
            new ExecutorFacialPhotoValidatorResolverStub(
                validator: new ExecutorFacialPhotoValidatorStub(
                    $validation
                )
            );

        $repository =
            new ExecutorFacialPhotoValidationRepositoryStub(
                target: $this->target(),
                result: $this->executionResult(
                    $validation
                ),
            );

        $executor = $this->executor(
            enabled: true,
            provider: null,
            scenario: null,
            resolver: $resolver,
            repository: $repository,
        );

        try {
            $executor->execute(
                new ExecuteFacialPhotoValidationCommand(
                    photoId: 'photo-1'
                )
            );

            $this->fail(
                'O provider ausente deveria bloquear a execução.'
            );
        } catch (
            FacialPhotoValidatorResolutionException $exception
        ) {
            $this->assertSame(
                'O provider de validação facial é obrigatório.',
                $exception->getMessage()
            );
        }

        $this->assertSame(0, $resolver->calls);
        $this->assertSame(0, $repository->findCalls);
        $this->assertSame(0, $repository->persistCalls);
    }

    public function test_it_preserves_safe_resolution_failures_before_loading_the_photo(): void
    {
        $resolutionFailure =
            FacialPhotoValidatorResolutionException::providerDisabled();

        $resolver =
            new ExecutorFacialPhotoValidatorResolverStub(
                validator: null,
                failure: $resolutionFailure,
            );

        $repository =
            new ExecutorFacialPhotoValidationRepositoryStub(
                target: $this->target(),
                result: null,
            );

        $executor = $this->executor(
            enabled: true,
            provider: 'simulator',
            scenario: 'approved',
            resolver: $resolver,
            repository: $repository,
        );

        try {
            $executor->execute(
                new ExecuteFacialPhotoValidationCommand(
                    photoId: 'photo-1'
                )
            );

            $this->fail(
                'A falha segura do resolver deveria ser preservada.'
            );
        } catch (
            FacialPhotoValidatorResolutionException $exception
        ) {
            $this->assertSame(
                $resolutionFailure,
                $exception
            );
        }

        $this->assertSame(1, $resolver->calls);

        $this->assertInstanceOf(
            FacialPhotoValidatorSelection::class,
            $resolver->selection
        );

        $this->assertSame(
            FacialPhotoValidatorProvider::Simulator,
            $resolver->selection?->provider
        );

        $this->assertSame(
            'approved',
            $resolver->selection?->scenario
        );

        $this->assertSame(0, $repository->findCalls);
        $this->assertSame(0, $repository->persistCalls);
    }

    public function test_it_resolves_the_validator_and_delegates_to_the_c5_core(): void
    {
        $target = $this->target();
        $validation = $this->approvedValidation();

        $expectedResult = $this->executionResult(
            $validation
        );

        $validator =
            new ExecutorFacialPhotoValidatorStub(
                $validation
            );

        $resolver =
            new ExecutorFacialPhotoValidatorResolverStub(
                validator: $validator
            );

        $repository =
            new ExecutorFacialPhotoValidationRepositoryStub(
                target: $target,
                result: $expectedResult,
            );

        $executor = $this->executor(
            enabled: true,
            provider: ' SIMULATOR ',
            scenario: ' APPROVED ',
            resolver: $resolver,
            repository: $repository,
        );

        $result = $executor->execute(
            new ExecuteFacialPhotoValidationCommand(
                photoId: 'photo-1',
                operatorUserId: 42,
            )
        );

        $this->assertSame(
            $expectedResult,
            $result
        );

        $this->assertSame(1, $resolver->calls);

        $this->assertInstanceOf(
            FacialPhotoValidatorSelection::class,
            $resolver->selection
        );

        $this->assertSame(
            FacialPhotoValidatorProvider::Simulator,
            $resolver->selection?->provider
        );

        $this->assertSame(
            'approved',
            $resolver->selection?->scenario
        );

        $this->assertSame(1, $validator->calls);

        $this->assertSame(
            '/tmp/synthetic-photo.jpg',
            $validator->receivedPath
        );

        $this->assertSame(1, $repository->findCalls);

        $this->assertSame(
            'photo-1',
            $repository->receivedPhotoId
        );

        $this->assertSame(1, $repository->persistCalls);

        $this->assertInstanceOf(
            FacialPhotoValidationPersistenceData::class,
            $repository->receivedData
        );

        $this->assertSame(
            $target,
            $repository->receivedData?->target
        );

        $this->assertSame(
            $validation,
            $repository->receivedData?->validation
        );

        $this->assertSame(
            FacialPhotoStatus::Approved,
            $repository->receivedData?->transition->to
        );

        $this->assertSame(
            42,
            $repository->receivedData?->operatorUserId
        );
    }

    private function executor(
        bool $enabled,
        ?string $provider,
        ?string $scenario,
        FacialPhotoValidatorResolver $resolver,
        ExecuteFacialPhotoValidationRepository $repository,
    ): ConfiguredFacialPhotoValidationExecutor {
        return new ConfiguredFacialPhotoValidationExecutor(
            enabled: $enabled,
            provider: $provider,
            scenario: $scenario,
            resolver: $resolver,
            repository: $repository,
            transitionPolicy: new FacialPhotoStatusTransitionPolicy,
        );
    }

    private function target(): FacialPhotoValidationTarget
    {
        return new FacialPhotoValidationTarget(
            photoId: 'photo-1',
            status: FacialPhotoStatus::PendingValidation,
            mediaId: 10,
            absolutePath: '/tmp/synthetic-photo.jpg',
            sha256: str_repeat('a', 64),
        );
    }

    private function approvedValidation(): FacialPhotoValidationResult
    {
        return new FacialPhotoValidationResult(
            validator: 'synthetic-validator',
            version: 'synthetic-v1',
            decision: FacialPhotoValidationDecision::Approved,
            faceCount: 1,
            metrics: [
                'confidence' => 0.99,
            ],
            issues: [],
        );
    }

    private function executionResult(
        FacialPhotoValidationResult $validation
    ): ExecuteFacialPhotoValidationResult {
        $transition =
            (new FacialPhotoStatusTransitionPolicy)
                ->transition(
                    currentStatus: FacialPhotoStatus::PendingValidation,
                    decision: $validation->decision,
                );

        return new ExecuteFacialPhotoValidationResult(
            photoId: 'photo-1',
            attemptId: 'attempt-1',
            attemptNumber: 1,
            validation: $validation,
            transition: $transition,
            validatedAt: new DateTimeImmutable(
                '2026-07-26 11:00:00'
            ),
        );
    }
}

final class ExecutorFacialPhotoValidatorResolverStub implements FacialPhotoValidatorResolver
{
    public int $calls = 0;

    public ?FacialPhotoValidatorSelection $selection =
        null;

    public function __construct(
        public ?FacialPhotoValidator $validator,
        public ?FacialPhotoValidatorResolutionException $failure =
            null,
    ) {}

    public function resolve(
        FacialPhotoValidatorSelection $selection
    ): FacialPhotoValidator {
        $this->calls++;
        $this->selection = $selection;

        if (
            $this->failure
            instanceof FacialPhotoValidatorResolutionException
        ) {
            throw $this->failure;
        }

        if (
            ! $this->validator
            instanceof FacialPhotoValidator
        ) {
            throw new RuntimeException(
                'O stub do resolver não possui validador.'
            );
        }

        return $this->validator;
    }
}

final class ExecutorFacialPhotoValidatorStub implements FacialPhotoValidator
{
    public int $calls = 0;

    public ?string $receivedPath = null;

    public function __construct(
        private FacialPhotoValidationResult $result,
    ) {}

    public function validate(
        string $absolutePath
    ): FacialPhotoValidationResult {
        $this->calls++;
        $this->receivedPath = $absolutePath;

        return $this->result;
    }
}

final class ExecutorFacialPhotoValidationRepositoryStub implements ExecuteFacialPhotoValidationRepository
{
    public int $findCalls = 0;

    public int $persistCalls = 0;

    public ?string $receivedPhotoId = null;

    public ?FacialPhotoValidationPersistenceData $receivedData =
        null;

    public function __construct(
        public ?FacialPhotoValidationTarget $target,
        public ?ExecuteFacialPhotoValidationResult $result,
    ) {}

    public function findTarget(
        string $photoId
    ): ?FacialPhotoValidationTarget {
        $this->findCalls++;
        $this->receivedPhotoId = $photoId;

        return $this->target;
    }

    public function persist(
        FacialPhotoValidationPersistenceData $data
    ): ExecuteFacialPhotoValidationResult {
        $this->persistCalls++;
        $this->receivedData = $data;

        if (
            ! $this->result
            instanceof ExecuteFacialPhotoValidationResult
        ) {
            throw new RuntimeException(
                'O stub do repositório não possui resultado.'
            );
        }

        return $this->result;
    }
}
