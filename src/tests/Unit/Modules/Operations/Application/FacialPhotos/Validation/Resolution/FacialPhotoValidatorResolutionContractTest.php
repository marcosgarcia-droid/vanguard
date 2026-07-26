<?php

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Validation\Resolution;

use App\Modules\Operations\Application\FacialPhotos\Validation\FacialPhotoValidator;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorProvider;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorResolutionException;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorResolver;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorSelection;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationResult;
use LogicException;
use PHPUnit\Framework\TestCase;

final class FacialPhotoValidatorResolutionContractTest extends TestCase
{
    public function test_it_normalizes_a_supported_provider(): void
    {
        $provider =
            FacialPhotoValidatorProvider::fromInput(
                '  SIMULATOR  '
            );

        $this->assertSame(
            FacialPhotoValidatorProvider::Simulator,
            $provider
        );

        $this->assertSame(
            'simulator',
            $provider->value
        );
    }

    public function test_it_rejects_a_missing_provider(): void
    {
        $this->expectException(
            FacialPhotoValidatorResolutionException::class
        );

        $this->expectExceptionMessage(
            'O provider de validação facial é obrigatório.'
        );

        FacialPhotoValidatorProvider::fromInput(
            '   '
        );
    }

    public function test_it_rejects_an_unsupported_provider(): void
    {
        $this->expectException(
            FacialPhotoValidatorResolutionException::class
        );

        $this->expectExceptionMessage(
            'O provider de validação facial informado não é suportado.'
        );

        FacialPhotoValidatorProvider::fromInput(
            'unknown-provider'
        );
    }

    public function test_it_normalizes_the_optional_scenario(): void
    {
        $selection =
            FacialPhotoValidatorSelection::fromInput(
                provider: ' SIMULATOR ',
                scenario: ' NO_FACE_DETECTED ',
            );

        $this->assertSame(
            FacialPhotoValidatorProvider::Simulator,
            $selection->provider
        );

        $this->assertSame(
            'no_face_detected',
            $selection->scenario
        );

        $withoutScenario =
            FacialPhotoValidatorSelection::fromInput(
                provider: 'simulator',
                scenario: '   ',
            );

        $this->assertNull(
            $withoutScenario->scenario
        );
    }

    public function test_it_exposes_only_safe_resolution_messages(): void
    {
        $this->assertSame(
            [
                'O cenário do simulador facial é obrigatório.',
                'O cenário informado para o simulador facial não é suportado.',
                'O provider de validação facial informado está desativado.',
                'O provider de validação facial informado não é permitido neste ambiente.',
            ],
            [
                FacialPhotoValidatorResolutionException::scenarioRequired()
                    ->getMessage(),
                FacialPhotoValidatorResolutionException::scenarioNotSupported()
                    ->getMessage(),
                FacialPhotoValidatorResolutionException::providerDisabled()
                    ->getMessage(),
                FacialPhotoValidatorResolutionException::providerNotAllowedInEnvironment()
                    ->getMessage(),
            ]
        );
    }

    public function test_it_defines_an_explicit_resolver_contract(): void
    {
        $validator =
            new class implements FacialPhotoValidator
            {
                public function validate(
                    string $absolutePath
                ): FacialPhotoValidationResult {
                    throw new LogicException(
                        'O stub não deve executar validação.'
                    );
                }
            };

        $resolver =
            new class($validator) implements FacialPhotoValidatorResolver
            {
                public function __construct(
                    private FacialPhotoValidator $validator,
                ) {}

                public function resolve(
                    FacialPhotoValidatorSelection $selection
                ): FacialPhotoValidator {
                    return $this->validator;
                }
            };

        $selection =
            FacialPhotoValidatorSelection::fromInput(
                provider: 'simulator',
                scenario: 'approved',
            );

        $this->assertInstanceOf(
            FacialPhotoValidatorResolver::class,
            $resolver
        );

        $this->assertSame(
            $validator,
            $resolver->resolve(
                $selection
            )
        );
    }
}
