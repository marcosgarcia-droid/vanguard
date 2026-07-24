<?php

namespace App\Modules\Operations\Domain\FacialPhotos;

enum FacialPhotoStatus: string
{
    case PendingValidation = 'pending_validation';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Outdated = 'outdated';

    public function label(): string
    {
        return match ($this) {
            self::PendingValidation => 'Aguardando validação',
            self::Approved => 'Aprovada',
            self::Rejected => 'Reprovada',
            self::Outdated => 'Desatualizada',
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

    public function isUsableForRecognition(): bool
    {
        return $this === self::Approved;
    }
}
