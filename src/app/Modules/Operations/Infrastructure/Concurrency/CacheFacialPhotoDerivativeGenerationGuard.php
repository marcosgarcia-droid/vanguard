<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Concurrency;

use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\FacialPhotoDerivativeGenerationGuard;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\FacialPhotoDerivativeGenerationLease;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeCommand;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeException;
use Illuminate\Support\Facades\Cache;

final readonly class CacheFacialPhotoDerivativeGenerationGuard implements FacialPhotoDerivativeGenerationGuard
{
    public function __construct(
        private int $lockSeconds
    ) {}

    public function acquire(
        GenerateFacialPhotoDerivativeCommand $command
    ): FacialPhotoDerivativeGenerationLease {
        $lock = Cache::lock(
            'vanguard:facial-photo:derivative:lock:'
                .$command->identity(),
            max(
                60,
                $this->lockSeconds
            )
        );

        if (! $lock->get()) {
            throw GenerateFacialPhotoDerivativeException::generationInProgress();
        }

        return new CacheFacialPhotoDerivativeGenerationLease(
            $lock
        );
    }
}
