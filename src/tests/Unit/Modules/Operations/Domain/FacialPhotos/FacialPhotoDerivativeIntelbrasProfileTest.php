<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Domain\FacialPhotos;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeProfile;
use PHPUnit\Framework\TestCase;

final class FacialPhotoDerivativeIntelbrasProfileTest extends TestCase
{
    public function test_it_exposes_an_intelbras_credential_profile_distinct_from_the_generic_profile(): void
    {
        $intelbras =
            FacialPhotoDerivativeProfile::intelbrasFacialCredential();

        self::assertSame(
            'intelbras_facial_credential',
            $intelbras->value
        );

        self::assertSame(
            FacialPhotoDerivativeProfile::INTELBRAS_FACIAL_CREDENTIAL,
            $intelbras->value
        );

        self::assertFalse(
            $intelbras->equals(
                FacialPhotoDerivativeProfile::vanguardNormalized()
            )
        );
    }
}
