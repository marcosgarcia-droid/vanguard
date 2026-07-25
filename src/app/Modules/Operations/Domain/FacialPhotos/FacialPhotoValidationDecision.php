<?php

namespace App\Modules\Operations\Domain\FacialPhotos;

enum FacialPhotoValidationDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Inconclusive = 'inconclusive';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Aprovada',
            self::Rejected => 'Reprovada',
            self::Inconclusive => 'Validação inconclusiva',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
