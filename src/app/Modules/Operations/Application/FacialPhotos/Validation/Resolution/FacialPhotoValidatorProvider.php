<?php

namespace App\Modules\Operations\Application\FacialPhotos\Validation\Resolution;

enum FacialPhotoValidatorProvider: string
{
    case Simulator = 'simulator';

    case LocalVision = 'local_vision';

    public static function fromInput(
        string $provider
    ): self {
        $normalized = strtolower(
            trim($provider)
        );

        if ($normalized === '') {
            throw FacialPhotoValidatorResolutionException::providerRequired();
        }

        return self::tryFrom($normalized)
            ?? throw FacialPhotoValidatorResolutionException::providerNotSupported();
    }
}
