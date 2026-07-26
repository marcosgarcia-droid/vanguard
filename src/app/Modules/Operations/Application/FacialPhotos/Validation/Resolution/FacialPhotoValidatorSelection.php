<?php

namespace App\Modules\Operations\Application\FacialPhotos\Validation\Resolution;

final readonly class FacialPhotoValidatorSelection
{
    public FacialPhotoValidatorProvider $provider;

    public ?string $scenario;

    public function __construct(
        FacialPhotoValidatorProvider $provider,
        ?string $scenario = null,
    ) {
        $this->provider = $provider;

        $normalizedScenario = strtolower(
            trim((string) $scenario)
        );

        $this->scenario = $normalizedScenario === ''
            ? null
            : $normalizedScenario;
    }

    public static function fromInput(
        string $provider,
        ?string $scenario = null,
    ): self {
        return new self(
            provider: FacialPhotoValidatorProvider::fromInput(
                $provider
            ),
            scenario: $scenario,
        );
    }
}
