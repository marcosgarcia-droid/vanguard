<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Preview;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalIssue;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;
use InvalidArgumentException;

final readonly class FacialPhotoPreviewIssue
{
    public const SOURCE_TECHNICAL = 'technical';

    public const SOURCE_FACIAL = 'facial';

    public function __construct(
        public string $source,
        public string $code,
        public string $label,
        public string $guidance,
    ) {
        if (
            ! in_array(
                $this->source,
                [
                    self::SOURCE_TECHNICAL,
                    self::SOURCE_FACIAL,
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'A origem da ocorrência da pré-validação é inválida.'
            );
        }

        foreach (
            [
                'code' => $this->code,
                'label' => $this->label,
                'guidance' => $this->guidance,
            ] as $field => $value
        ) {
            if (trim($value) === '') {
                throw new InvalidArgumentException(
                    "O campo {$field} da ocorrência é obrigatório."
                );
            }
        }
    }

    public static function fromTechnical(
        FacialPhotoTechnicalIssue $issue
    ): self {
        return new self(
            source: self::SOURCE_TECHNICAL,
            code: $issue->value,
            label: $issue->label(),
            guidance: $issue->guidance(),
        );
    }

    public static function fromFacial(
        FacialPhotoValidationIssue $issue
    ): self {
        return new self(
            source: self::SOURCE_FACIAL,
            code: $issue->value,
            label: $issue->label(),
            guidance: $issue->guidance(),
        );
    }

    /**
     * @return array{
     *     source: string,
     *     source_label: string,
     *     code: string,
     *     label: string,
     *     guidance: string
     * }
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'source_label' => $this->sourceLabel(),
            'code' => $this->code,
            'label' => $this->label,
            'guidance' => $this->guidance,
        ];
    }

    private function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_TECHNICAL => 'Qualidade da imagem',

            self::SOURCE_FACIAL => 'Posicionamento facial',
        };
    }
}
