<?php

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Validation\Execute;

use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationCommand;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationException;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationRepository;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationResult;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationUseCase;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\FacialPhotoValidationPersistenceData;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\FacialPhotoValidationTarget;
use App\Modules\Operations\Application\FacialPhotos\Validation\FacialPhotoValidator;
use App\Modules\Operations\Application\FacialPhotos\Validation\ValidateFacialPhotoUseCase;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatusTransitionPolicy;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationResult;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class ExecuteFacialPhotoValidationUseCaseTest extends TestCase
{
    public function test_it_validates_the_target_and_delegates_atomic_persistence(): void
    {
        $target = $this->target();
        $validation = $this->approvedValidation();

        $expectedResult = $this->executionResult(
            validation: $validation,
            statusBefore: FacialPhotoStatus::PendingValidation,
        );

        $repository =
            new ExecuteFacialPhotoValidationRepositoryStub(
                target: $target,
                result: $expectedResult,
            );

        $validator =
            new FacialPhotoValidatorStub(
                result: $validation
            );

        $useCase = $this->useCase(
            repository: $repository,
            validator: $validator,
        );

        $result = $useCase->execute(
            new ExecuteFacialPhotoValidationCommand(
                photoId: 'photo-1',
                operatorUserId: 42,
            )
        );

        $this->assertSame(
            $expectedResult,
            $result
        );

        $this->assertSame(
            '/tmp/synthetic-photo.jpg',
            $validator->receivedPath
        );

        $this->assertSame(
            1,
            $validator->calls
        );

        $this->assertSame(
            1,
            $repository->persistCalls
        );

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
            $repository
                ->receivedData
                ?->transition
                ->to
        );

        $this->assertSame(
            42,
            $repository->receivedData?->operatorUserId
        );
    }

    public function test_it_rejects_a_terminal_photo_before_calling_the_validator(): void
    {
        $repository =
            new ExecuteFacialPhotoValidationRepositoryStub(
                target: $this->target(
                    FacialPhotoStatus::Approved
                ),
                result: null,
            );

        $validator =
            new FacialPhotoValidatorStub(
                result: $this->approvedValidation()
            );

        $useCase = $this->useCase(
            repository: $repository,
            validator: $validator,
        );

        try {
            $useCase->execute(
                new ExecuteFacialPhotoValidationCommand(
                    photoId: 'photo-1'
                )
            );

            $this->fail(
                'A foto terminal não deveria ser validada.'
            );
        } catch (
            ExecuteFacialPhotoValidationException $exception
        ) {
            $this->assertSame(
                'A foto facial com situação “Aprovada” não pode ser validada novamente.',
                $exception->getMessage()
            );
        }

        $this->assertSame(0, $validator->calls);
        $this->assertSame(0, $repository->persistCalls);
    }

    public function test_it_wraps_validator_failures_without_persisting(): void
    {
        $internalFailure = new RuntimeException(
            'falha interna do validador'
        );

        $repository =
            new ExecuteFacialPhotoValidationRepositoryStub(
                target: $this->target(),
                result: null,
            );

        $validator =
            new FacialPhotoValidatorStub(
                result: null,
                failure: $internalFailure,
            );

        $useCase = $this->useCase(
            repository: $repository,
            validator: $validator,
        );

        try {
            $useCase->execute(
                new ExecuteFacialPhotoValidationCommand(
                    photoId: 'photo-1'
                )
            );

            $this->fail(
                'A falha do validador deveria interromper a execução.'
            );
        } catch (
            ExecuteFacialPhotoValidationException $exception
        ) {
            $this->assertSame(
                'Não foi possível executar a validação facial.',
                $exception->getMessage()
            );

            $this->assertSame(
                $internalFailure,
                $exception->getPrevious()
            );
        }

        $this->assertSame(1, $validator->calls);
        $this->assertSame(0, $repository->persistCalls);
    }

    public function test_it_wraps_unexpected_persistence_failures(): void
    {
        $validation = new FacialPhotoValidationResult(
            validator: 'synthetic-validator',
            version: 'synthetic-v1',
            decision: FacialPhotoValidationDecision::Inconclusive,
            faceCount: 0,
            metrics: [
                'available' => false,
            ],
            issues: [
                FacialPhotoValidationIssue::ValidatorUnavailable,
            ],
        );

        $internalFailure = new RuntimeException(
            'falha interna da persistência'
        );

        $repository =
            new ExecuteFacialPhotoValidationRepositoryStub(
                target: $this->target(),
                result: null,
                persistFailure: $internalFailure,
            );

        $validator =
            new FacialPhotoValidatorStub(
                result: $validation
            );

        $useCase = $this->useCase(
            repository: $repository,
            validator: $validator,
        );

        try {
            $useCase->execute(
                new ExecuteFacialPhotoValidationCommand(
                    photoId: 'photo-1'
                )
            );

            $this->fail(
                'A falha da persistência deveria ser protegida.'
            );
        } catch (
            ExecuteFacialPhotoValidationException $exception
        ) {
            $this->assertSame(
                'Não foi possível registrar o resultado da validação facial.',
                $exception->getMessage()
            );

            $this->assertSame(
                $internalFailure,
                $exception->getPrevious()
            );
        }

        $this->assertSame(1, $validator->calls);
        $this->assertSame(1, $repository->persistCalls);
    }

    private function useCase(
        ExecuteFacialPhotoValidationRepository $repository,
        FacialPhotoValidator $validator,
    ): ExecuteFacialPhotoValidationUseCase {
        return new ExecuteFacialPhotoValidationUseCase(
            repository: $repository,
            validateFacialPhoto: new ValidateFacialPhotoUseCase(
                $validator
            ),
            transitionPolicy: new FacialPhotoStatusTransitionPolicy,
        );
    }

    private function target(
        FacialPhotoStatus $status =
            FacialPhotoStatus::PendingValidation,
    ): FacialPhotoValidationTarget {
        return new FacialPhotoValidationTarget(
            photoId: 'photo-1',
            status: $status,
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
        FacialPhotoValidationResult $validation,
        FacialPhotoStatus $statusBefore,
    ): ExecuteFacialPhotoValidationResult {
        $transition =
            (new FacialPhotoStatusTransitionPolicy)
                ->transition(
                    currentStatus: $statusBefore,
                    decision: $validation->decision,
                );

        return new ExecuteFacialPhotoValidationResult(
            photoId: 'photo-1',
            attemptId: 'attempt-1',
            attemptNumber: 1,
            validation: $validation,
            transition: $transition,
            validatedAt: new DateTimeImmutable(
                '2026-07-25 17:00:00'
            ),
        );
    }
}

final class ExecuteFacialPhotoValidationRepositoryStub implements ExecuteFacialPhotoValidationRepository
{
    public int $findCalls = 0;

    public int $persistCalls = 0;

    public ?FacialPhotoValidationPersistenceData $receivedData =
        null;

    public function __construct(
        public ?FacialPhotoValidationTarget $target,
        public ?ExecuteFacialPhotoValidationResult $result,
        public ?Throwable $findFailure = null,
        public ?Throwable $persistFailure = null,
    ) {}

    public function findTarget(
        string $photoId
    ): ?FacialPhotoValidationTarget {
        $this->findCalls++;

        if ($this->findFailure instanceof Throwable) {
            throw $this->findFailure;
        }

        return $this->target;
    }

    public function persist(
        FacialPhotoValidationPersistenceData $data
    ): ExecuteFacialPhotoValidationResult {
        $this->persistCalls++;
        $this->receivedData = $data;

        if ($this->persistFailure instanceof Throwable) {
            throw $this->persistFailure;
        }

        if (
            ! $this->result
            instanceof ExecuteFacialPhotoValidationResult
        ) {
            throw new RuntimeException(
                'O stub não possui um resultado de persistência.'
            );
        }

        return $this->result;
    }
}

final class FacialPhotoValidatorStub implements FacialPhotoValidator
{
    public int $calls = 0;

    public ?string $receivedPath = null;

    public function __construct(
        public ?FacialPhotoValidationResult $result,
        public ?Throwable $failure = null,
    ) {}

    public function validate(
        string $absolutePath
    ): FacialPhotoValidationResult {
        $this->calls++;
        $this->receivedPath = $absolutePath;

        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        if (
            ! $this->result
            instanceof FacialPhotoValidationResult
        ) {
            throw new RuntimeException(
                'O stub não possui um resultado facial.'
            );
        }

        return $this->result;
    }
}
