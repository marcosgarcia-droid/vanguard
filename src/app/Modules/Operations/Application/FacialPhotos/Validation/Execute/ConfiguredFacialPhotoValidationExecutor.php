<?php

namespace App\Modules\Operations\Application\FacialPhotos\Validation\Execute;

use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorResolver;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorSelection;
use App\Modules\Operations\Application\FacialPhotos\Validation\ValidateFacialPhotoUseCase;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatusTransitionPolicy;

final readonly class ConfiguredFacialPhotoValidationExecutor implements FacialPhotoValidationExecutor
{
    public function __construct(
        private bool $enabled,
        private ?string $provider,
        private ?string $scenario,
        private FacialPhotoValidatorResolver $resolver,
        private ExecuteFacialPhotoValidationRepository $repository,
        private FacialPhotoStatusTransitionPolicy $transitionPolicy,
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

        return $useCase->execute(
            $command
        );
    }
}
