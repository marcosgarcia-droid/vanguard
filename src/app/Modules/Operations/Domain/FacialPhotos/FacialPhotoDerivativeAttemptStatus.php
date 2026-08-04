<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain\FacialPhotos;

enum FacialPhotoDerivativeAttemptStatus: string
{
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'Em processamento',
            self::Succeeded => 'Concluída',
            self::Failed => 'Falhou',
            self::Skipped => 'Ignorada com segurança',
        };
    }

    public function isTerminal(): bool
    {
        return $this !== self::Processing;
    }
}
