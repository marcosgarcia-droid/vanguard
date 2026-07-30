<?php

namespace App\Modules\Operations\Infrastructure\Images\Resolution;

use App\Modules\Operations\Application\FacialPhotos\Validation\FacialPhotoValidator;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorProvider;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorResolutionException;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorResolver;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorSelection;
use App\Modules\Operations\Infrastructure\Images\LocalVision\LocalVisionFacialPhotoValidator;
use App\Modules\Operations\Infrastructure\Images\Simulator\SimulatedFacialPhotoValidationScenario;
use App\Modules\Operations\Infrastructure\Images\Simulator\SimulatedFacialPhotoValidator;

final readonly class ConfiguredFacialPhotoValidatorResolver implements FacialPhotoValidatorResolver
{
    /**
     * @var list<string>
     */
    private const ALLOWED_SIMULATOR_ENVIRONMENTS = [
        'local',
        'testing',
    ];

    private string $environment;

    public function __construct(
        string $environment,
        private bool $simulatorEnabled,
        private bool $localVisionEnabled = false,
        private ?LocalVisionFacialPhotoValidator $localVisionValidator = null,
    ) {
        $this->environment = strtolower(
            trim($environment)
        );
    }

    public function resolve(
        FacialPhotoValidatorSelection $selection
    ): FacialPhotoValidator {
        return match ($selection->provider) {
            FacialPhotoValidatorProvider::Simulator => $this->resolveSimulator(
                $selection
            ),
            FacialPhotoValidatorProvider::LocalVision => $this->resolveLocalVision(),
        };
    }

    private function resolveLocalVision(): FacialPhotoValidator
    {
        if (! $this->localVisionEnabled) {
            throw FacialPhotoValidatorResolutionException::providerDisabled();
        }

        return $this->localVisionValidator
            ?? new LocalVisionFacialPhotoValidator;
    }

    private function resolveSimulator(
        FacialPhotoValidatorSelection $selection
    ): FacialPhotoValidator {
        if (! $this->simulatorEnabled) {
            throw FacialPhotoValidatorResolutionException::providerDisabled();
        }

        if (
            ! in_array(
                $this->environment,
                self::ALLOWED_SIMULATOR_ENVIRONMENTS,
                true
            )
        ) {
            throw FacialPhotoValidatorResolutionException::providerNotAllowedInEnvironment();
        }

        if ($selection->scenario === null) {
            throw FacialPhotoValidatorResolutionException::scenarioRequired();
        }

        $scenario =
            SimulatedFacialPhotoValidationScenario::tryFrom(
                $selection->scenario
            );

        if (
            ! $scenario
            instanceof SimulatedFacialPhotoValidationScenario
        ) {
            throw FacialPhotoValidatorResolutionException::scenarioNotSupported();
        }

        return new SimulatedFacialPhotoValidator(
            $scenario
        );
    }
}
