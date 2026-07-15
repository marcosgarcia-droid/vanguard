<?php

namespace App\Modules\Operations\Domain\AccessControl;

enum AccessEventOperationalExecutionSource: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Automática',
            self::Manual => 'Manual',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $source): array => [
                $source->value => $source->label(),
            ])
            ->all();
    }
}
