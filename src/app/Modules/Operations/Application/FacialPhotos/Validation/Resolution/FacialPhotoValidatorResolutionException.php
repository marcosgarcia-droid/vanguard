<?php

namespace App\Modules\Operations\Application\FacialPhotos\Validation\Resolution;

use RuntimeException;

final class FacialPhotoValidatorResolutionException extends RuntimeException
{
    public static function providerRequired(): self
    {
        return new self(
            'O provider de validação facial é obrigatório.'
        );
    }

    public static function providerNotSupported(): self
    {
        return new self(
            'O provider de validação facial informado não é suportado.'
        );
    }

    public static function scenarioRequired(): self
    {
        return new self(
            'O cenário do simulador facial é obrigatório.'
        );
    }

    public static function scenarioNotSupported(): self
    {
        return new self(
            'O cenário informado para o simulador facial não é suportado.'
        );
    }

    public static function providerDisabled(): self
    {
        return new self(
            'O provider de validação facial informado está desativado.'
        );
    }

    public static function providerNotAllowedInEnvironment(): self
    {
        return new self(
            'O provider de validação facial informado não é permitido neste ambiente.'
        );
    }
}
