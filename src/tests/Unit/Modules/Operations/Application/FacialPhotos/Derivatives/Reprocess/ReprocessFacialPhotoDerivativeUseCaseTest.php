<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess;

use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeCommand;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeCommand;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeContext;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeException;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeRepository;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeUseCase;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Schedule\FacialPhotoDerivativeAfterCommitScheduler;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSubjectType;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ReprocessFacialPhotoDerivativeUseCaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'facial_photos.normalization.enabled',
            true
        );

        config()->set(
            'facial_photos.normalization.async_generation.enabled',
            true
        );

        config()->set(
            'facial_photos.normalization.default_profile',
            'vanguard_normalized'
        );

        config()->set(
            'facial_photos.normalization.policy_version',
            'vanguard-normalization-v1'
        );

        config()->set(
            'facial_photos.normalization.normalizer',
            'spatie-gd'
        );

        config()->set(
            'facial_photos.normalization.normalizer_version',
            'spatie-gd-v1'
        );
    }

    public function test_it_schedules_an_authorized_reprocessing_request(): void
    {
        $photoId = (string) Str::uuid();
        $scheduledCommand = null;

        $repository =
            new class($photoId) implements ReprocessFacialPhotoDerivativeRepository
            {
                public function __construct(
                    private readonly string $photoId
                ) {}

                public function prepare(
                    ReprocessFacialPhotoDerivativeCommand $command,
                    string $profile,
                    string $policyVersion,
                ): ReprocessFacialPhotoDerivativeContext {
                    return new ReprocessFacialPhotoDerivativeContext(
                        photoId: $this->photoId,
                        requesterName: 'OPERADOR A5F',
                        previousStatus: FacialPhotoDerivativeStatus::Failed,
                    );
                }
            };

        $scheduler =
            new class($scheduledCommand) implements FacialPhotoDerivativeAfterCommitScheduler
            {
                public ?GenerateFacialPhotoDerivativeCommand $captured = null;

                public function __construct(
                    mixed &$captured
                ) {
                    $this->external = &$captured;
                }

                private mixed $external;

                public function schedule(
                    GenerateFacialPhotoDerivativeCommand $command
                ): bool {
                    $this->captured = $command;
                    $this->external = $command;

                    return true;
                }
            };

        $command =
            new ReprocessFacialPhotoDerivativeCommand(
                subjectType: FacialPhotoSubjectType::Visitor,
                subjectId: (string) Str::uuid(),
                operatorUserId: 10,
                requestId: (string) Str::uuid(),
            );

        $result =
            (new ReprocessFacialPhotoDerivativeUseCase(
                repository: $repository,
                scheduler: $scheduler,
            ))->execute(
                $command
            );

        $this->assertTrue(
            $result->scheduled
        );

        $this->assertSame(
            $photoId,
            $result->photoId
        );

        $this->assertSame(
            FacialPhotoDerivativeStatus::Failed,
            $result->previousStatus
        );

        $this->assertInstanceOf(
            GenerateFacialPhotoDerivativeCommand::class,
            $scheduledCommand
        );

        $this->assertSame(
            $photoId,
            $scheduledCommand->photoId
        );

        $this->assertSame(
            10,
            $scheduledCommand->requestedBy
        );

        $this->assertSame(
            'OPERADOR A5F',
            $scheduledCommand->requesterName
        );
    }

    public function test_it_fails_closed_when_generation_is_disabled(): void
    {
        config()->set(
            'facial_photos.normalization.enabled',
            false
        );

        $repository =
            new class implements ReprocessFacialPhotoDerivativeRepository
            {
                public function prepare(
                    ReprocessFacialPhotoDerivativeCommand $command,
                    string $profile,
                    string $policyVersion,
                ): ReprocessFacialPhotoDerivativeContext {
                    throw new \LogicException(
                        'O repositório não deveria ser chamado.'
                    );
                }
            };

        $scheduler =
            new class implements FacialPhotoDerivativeAfterCommitScheduler
            {
                public function schedule(
                    GenerateFacialPhotoDerivativeCommand $command
                ): bool {
                    throw new \LogicException(
                        'O scheduler não deveria ser chamado.'
                    );
                }
            };

        try {
            (new ReprocessFacialPhotoDerivativeUseCase(
                repository: $repository,
                scheduler: $scheduler,
            ))->execute(
                new ReprocessFacialPhotoDerivativeCommand(
                    subjectType: FacialPhotoSubjectType::Visitor,
                    subjectId: (string) Str::uuid(),
                    operatorUserId: 1,
                    requestId: (string) Str::uuid(),
                )
            );

            $this->fail(
                'A operação deveria permanecer desativada.'
            );
        } catch (
            ReprocessFacialPhotoDerivativeException $exception
        ) {
            $this->assertSame(
                'facial_derivative_generation_disabled',
                $exception->failureCode
            );
        }
    }

    public function test_it_rejects_a_scheduler_that_does_not_accept_the_request(): void
    {
        $repository =
            new class implements ReprocessFacialPhotoDerivativeRepository
            {
                public function prepare(
                    ReprocessFacialPhotoDerivativeCommand $command,
                    string $profile,
                    string $policyVersion,
                ): ReprocessFacialPhotoDerivativeContext {
                    return new ReprocessFacialPhotoDerivativeContext(
                        photoId: (string) Str::uuid(),
                        requesterName: 'OPERADOR A5F',
                        previousStatus: null,
                    );
                }
            };

        $scheduler =
            new class implements FacialPhotoDerivativeAfterCommitScheduler
            {
                public function schedule(
                    GenerateFacialPhotoDerivativeCommand $command
                ): bool {
                    return false;
                }
            };

        $this->expectException(
            ReprocessFacialPhotoDerivativeException::class
        );

        $this->expectExceptionMessage(
            'Não foi possível solicitar o reprocessamento'
        );

        (new ReprocessFacialPhotoDerivativeUseCase(
            repository: $repository,
            scheduler: $scheduler,
        ))->execute(
            new ReprocessFacialPhotoDerivativeCommand(
                subjectType: FacialPhotoSubjectType::Visitor,
                subjectId: (string) Str::uuid(),
                operatorUserId: 1,
                requestId: (string) Str::uuid(),
            )
        );
    }
}
