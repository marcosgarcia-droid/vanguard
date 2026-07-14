<?php

namespace App\Modules\Operations\Domain\AccessControl;

enum AccessEventDirection: string
{
    case Entry = 'entry';
    case Exit = 'exit';

    public function label(): string
    {
        return match ($this) {
            self::Entry => 'Entrada',
            self::Exit => 'Saída',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $direction): array => [
                $direction->value => $direction->label(),
            ])
            ->all();
    }
}
