<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain\FacialPhotos;

enum FacialPhotoPreviewDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Inconclusive = 'inconclusive';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Foto aprovada',
            self::Rejected => 'Foto precisa ser refeita',
            self::Inconclusive => 'Validação inconclusiva',
        };
    }

    public function canUsePhoto(): bool
    {
        return $this !== self::Rejected;
    }
}
