<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain\FacialPhotos;

enum FacialPhotoDerivativeStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando preparação',
            self::Processing => 'Em preparação',
            self::Ready => 'Preparada',
            self::Failed => 'Falha na preparação',
            self::Superseded => 'Substituída',
        };
    }

    public function isTerminal(): bool
    {
        return in_array(
            $this,
            [
                self::Ready,
                self::Failed,
                self::Superseded,
            ],
            true
        );
    }

    public function isUsable(): bool
    {
        return $this === self::Ready;
    }
}
