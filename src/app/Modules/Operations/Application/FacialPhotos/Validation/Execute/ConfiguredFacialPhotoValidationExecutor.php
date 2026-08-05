<?php

namespace App\Modules\Operations\Application\FacialPhotos\Validation\Execute;

use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeCommand;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Schedule\FacialPhotoDerivativeAfterCommitScheduler;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorResolver;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorSelection;
use App\Modules\Operations\Application\FacialPhotos\Validation\ValidateFacialPhotoUseCase;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatusTransitionPolicy;
use Closure;
use Throwable;

final readonly class ConfiguredFacialPhotoValidationExecutor implements FacialPhotoValidationExecutor
{
    public function __construct(
        private bool $enabled,
        private ?string $provider,
        private ?string $scenario,
        private FacialPhotoValidatorResolver $resolver,
        private ExecuteFacialPhotoValidationRepository $repository,
        private FacialPhotoStatusTransitionPolicy $transitionPolicy,
        private ?FacialPhotoDerivativeAfterCommitScheduler $derivativeScheduler = null,
        private bool $derivativeSchedulingEnabled = false,
        private string $derivativeProfile = 'vanguard_normalized',
        private string $derivativePolicyVersion = 'vanguard-normalization-v1',
        private string $derivativeNormalizer = 'spatie-gd',
        private string $derivativeNormalizerVersion = 'spatie-gd-v1',
        private ?Closure $derivativeSchedulingFailureReporter = null,
    ) {}

    public function execute(
        ExecuteFacialPhotoValidationCommand $command
    ): ExecuteFacialPhotoValidationResult {
        if (! $this->enabled) {
            throw ExecuteFacialPhotoValidationException::validationDisabled();
        }

        $selection =
            FacialPhotoValidatorSelection::fromInput(
                provider: (string) $this->provider,
                scenario: $this->scenario,
            );

        $validator = $this->resolver->resolve(
            $selection
        );

        $useCase = new ExecuteFacialPhotoValidationUseCase(
            repository: $this->repository,
            validateFacialPhoto: new ValidateFacialPhotoUseCase(
                $validator
            ),
            transitionPolicy: $this->transitionPolicy,
        );

        $result = $useCase->execute(
            $command
        );

        $this->scheduleDerivativeAfterApproval(
            result: $result,
            operatorUserId: $command->operatorUserId,
        );

        return $result;
    }

    private function scheduleDerivativeAfterApproval(
        ExecuteFacialPhotoValidationResult $result,
        ?int $operatorUserId,
    ): void {
        if (
            ! $this->derivativeSchedulingEnabled
            || ! $this->derivativeScheduler instanceof FacialPhotoDerivativeAfterCommitScheduler
            || ! $result->isApproved()
            || ! $result->changedStatus()
        ) {
            return;
        }

        try {
            $this->derivativeScheduler->schedule(
                new GenerateFacialPhotoDerivativeCommand(
                    photoId: $result->photoId,
                    profile: $this->derivativeProfile,
                    policyVersion: $this->derivativePolicyVersion,
                    normalizer: $this->derivativeNormalizer,
                    normalizerVersion: $this->derivativeNormalizerVersion,
                    requestedBy: $operatorUserId,
                    requesterName: null,
                )
            );
        } catch (Throwable $throwable) {
            $this->reportSchedulingFailure(
                $throwable
            );
        }
    }

    private function reportSchedulingFailure(
        Throwable $throwable
    ): void {
        if (
            ! $this->derivativeSchedulingFailureReporter
                instanceof Closure
        ) {
            return;
        }

        try {
            ($this->derivativeSchedulingFailureReporter)(
                $throwable
            );
        } catch (Throwable) {
        }
    }
}
