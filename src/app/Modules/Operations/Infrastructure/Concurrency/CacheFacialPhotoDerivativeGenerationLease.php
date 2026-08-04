<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Concurrency;

use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\FacialPhotoDerivativeGenerationLease;
use Illuminate\Contracts\Cache\Lock;

final class CacheFacialPhotoDerivativeGenerationLease implements FacialPhotoDerivativeGenerationLease
{
    private bool $released = false;

    public function __construct(
        private readonly Lock $lock
    ) {}

    public function release(): void
    {
        if ($this->released) {
            return;
        }

        $this->lock->release();
        $this->released = true;
    }
}
