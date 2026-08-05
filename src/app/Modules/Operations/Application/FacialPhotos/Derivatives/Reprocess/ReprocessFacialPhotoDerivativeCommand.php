<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess;

use InvalidArgumentException;

final readonly class ReprocessFacialPhotoDerivativeCommand
{
    public function __construct(
        public string $visitorId,
        public int $operatorUserId,
        public string $requestId,
    ) {
        self::assertUuid(
            $this->visitorId,
            'visitante'
        );

        self::assertUuid(
            $this->requestId,
            'solicitação'
        );

        if ($this->operatorUserId < 1) {
            throw new InvalidArgumentException(
                'O operador da solicitação é inválido.'
            );
        }
    }

    private static function assertUuid(
        string $value,
        string $field
    ): void {
        if (
            preg_match(
                '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i',
                $value
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'O identificador de %s é inválido.',
                    $field
                )
            );
        }
    }
}
