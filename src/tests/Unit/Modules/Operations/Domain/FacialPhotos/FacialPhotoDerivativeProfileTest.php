<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Domain\FacialPhotos;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeAttemptStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeProfile;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FacialPhotoDerivativeProfileTest extends TestCase
{
    #[DataProvider('validProfiles')]
    public function test_it_accepts_safe_profiles(
        string $profile
    ): void {
        $value = FacialPhotoDerivativeProfile::from(
            $profile
        );

        $this->assertSame(
            $profile,
            $value->value
        );

        $this->assertSame(
            $profile,
            (string) $value
        );
    }

    #[DataProvider('invalidProfiles')]
    public function test_it_rejects_invalid_profiles(
        string $profile
    ): void {
        $this->expectException(
            InvalidArgumentException::class
        );

        FacialPhotoDerivativeProfile::from(
            $profile
        );
    }

    public function test_it_exposes_the_vanguard_profile(): void
    {
        $profile =
            FacialPhotoDerivativeProfile::vanguardNormalized();

        $this->assertSame(
            FacialPhotoDerivativeProfile::VANGUARD_NORMALIZED,
            $profile->value
        );

        $this->assertTrue(
            $profile->equals(
                FacialPhotoDerivativeProfile::from(
                    'vanguard_normalized'
                )
            )
        );
    }

    public function test_derivative_statuses_are_explicit(): void
    {
        $this->assertTrue(
            FacialPhotoDerivativeStatus::Ready->isUsable()
        );

        $this->assertFalse(
            FacialPhotoDerivativeStatus::Failed->isUsable()
        );

        $this->assertTrue(
            FacialPhotoDerivativeStatus::Superseded
                ->isTerminal()
        );

        $this->assertFalse(
            FacialPhotoDerivativeStatus::Processing
                ->isTerminal()
        );
    }

    public function test_attempt_statuses_are_explicit(): void
    {
        $this->assertFalse(
            FacialPhotoDerivativeAttemptStatus::Processing
                ->isTerminal()
        );

        $this->assertTrue(
            FacialPhotoDerivativeAttemptStatus::Succeeded
                ->isTerminal()
        );

        $this->assertSame(
            'Ignorada com segurança',
            FacialPhotoDerivativeAttemptStatus::Skipped
                ->label()
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validProfiles(): array
    {
        return [
            'VANGUARD' => [
                'vanguard_normalized',
            ],
            'Perfil versionado' => [
                'vanguard.normalized-v2',
            ],
            'Perfil futuro documentado' => [
                'intelbras:ss-3532-mf:documented-v1',
            ],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidProfiles(): array
    {
        return [
            'Vazio' => [''],
            'Somente espaços' => ['   '],
            'Maiúsculas' => ['VANGUARD_NORMALIZED'],
            'Espaços internos' => [
                'vanguard normalized',
            ],
            'Barra de diretório' => [
                'vanguard/normalized',
            ],
            'Separadores consecutivos' => [
                'vanguard..normalized',
            ],
            'Acima do limite' => [
                str_repeat('a', 101),
            ],
        ];
    }
}
