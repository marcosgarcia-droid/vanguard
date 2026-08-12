<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSubjectType;
use InvalidArgumentException;

final readonly class ReprocessFacialPhotoDerivativeCommand
{
    public function __construct(
        public FacialPhotoSubjectType $subjectType,
        public string $subjectId,
        public int $operatorUserId,
        public string $requestId,
    ) {
        self::assertUuid(
            $this->subjectId,
            match ($this->subjectType) {
                FacialPhotoSubjectType::Visitor => 'visitante',
                FacialPhotoSubjectType::Employee => 'funcionário',
            }
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
