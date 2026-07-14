<?php

namespace App\Modules\Operations\Domain\AccessControl;

enum AccessDeviceDirection: string
{
    case Entry = 'entry';
    case Exit = 'exit';
    case Bidirectional = 'bidirectional';

    public function label(): string
    {
        return match ($this) {
            self::Entry => 'Entrada',
            self::Exit => 'Saída',
            self::Bidirectional => 'Entrada e saída',
        };
    }

    public function accepts(
        AccessEventDirection $eventDirection
    ): bool {
        return match ($this) {
            self::Entry => $eventDirection === AccessEventDirection::Entry,
            self::Exit => $eventDirection === AccessEventDirection::Exit,
            self::Bidirectional => true,
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
