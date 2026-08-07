<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Images\Normalization;

use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationException;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationResult;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizer;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeProfile;
use App\Modules\Operations\Infrastructure\Images\Normalization\MappedFacialPhotoNormalizerResolver;
use PHPUnit\Framework\TestCase;

final class MappedFacialPhotoNormalizerResolverTest extends TestCase
{
    public function test_it_resolves_each_profile_explicitly(): void
    {
        $vanguard =
            $this->normalizerStub();

        $intelbras =
            $this->normalizerStub();

        $resolver =
            new MappedFacialPhotoNormalizerResolver([
                FacialPhotoDerivativeProfile::VANGUARD_NORMALIZED => $vanguard,

                FacialPhotoDerivativeProfile::INTELBRAS_FACIAL_CREDENTIAL => $intelbras,
            ]);

        self::assertSame(
            $vanguard,
            $resolver->resolve(
                FacialPhotoDerivativeProfile::vanguardNormalized()
            )
        );

        self::assertSame(
            $intelbras,
            $resolver->resolve(
                FacialPhotoDerivativeProfile::intelbrasFacialCredential()
            )
        );
    }

    public function test_it_fails_closed_for_an_unmapped_profile(): void
    {
        $resolver =
            new MappedFacialPhotoNormalizerResolver([
                FacialPhotoDerivativeProfile::VANGUARD_NORMALIZED => $this->normalizerStub(),
            ]);

        $this->expectException(
            FacialPhotoNormalizationException::class
        );

        $resolver->resolve(
            FacialPhotoDerivativeProfile::from(
                'unknown_profile'
            )
        );
    }

    private function normalizerStub(): FacialPhotoNormalizer
    {
        return new class implements FacialPhotoNormalizer
        {
            public function normalize(
                string $absoluteSourcePath
            ): FacialPhotoNormalizationResult {
                throw new \LogicException(
                    'O stub não executa normalização.'
                );
            }
        };
    }
}
