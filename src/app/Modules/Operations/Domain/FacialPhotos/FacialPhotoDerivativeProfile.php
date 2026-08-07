<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain\FacialPhotos;

use InvalidArgumentException;
use Stringable;

final readonly class FacialPhotoDerivativeProfile implements Stringable
{
    public const VANGUARD_NORMALIZED = 'vanguard_normalized';

    public const INTELBRAS_FACIAL_CREDENTIAL =
        'intelbras_facial_credential';

    private const MAXIMUM_LENGTH = 100;

    private const PATTERN =
        '/\A[a-z0-9]+(?:[._:-][a-z0-9]+)*\z/';

    private function __construct(
        public string $value
    ) {}

    public static function from(
        string $value
    ): self {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new InvalidArgumentException(
                'O perfil da derivação facial é obrigatório.'
            );
        }

        if (mb_strlen($normalized) > self::MAXIMUM_LENGTH) {
            throw new InvalidArgumentException(
                'O perfil da derivação facial excede o limite permitido.'
            );
        }

        if (
            preg_match(
                self::PATTERN,
                $normalized
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'O perfil da derivação facial possui formato inválido.'
            );
        }

        return new self($normalized);
    }

    public static function vanguardNormalized(): self
    {
        return self::from(
            self::VANGUARD_NORMALIZED
        );
    }

    public static function intelbrasFacialCredential(): self
    {
        return self::from(
            self::INTELBRAS_FACIAL_CREDENTIAL
        );
    }

    public function equals(
        self $other
    ): bool {
        return hash_equals(
            $this->value,
            $other->value
        );
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
