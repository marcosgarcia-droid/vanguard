<?php

namespace App\Modules\Operations\Domain\AccessControl;

enum AccessEventCollectionMode: string
{
    case Disabled = 'disabled';
    case Polling = 'polling';
    case Realtime = 'realtime';

    public function label(): string
    {
        return match ($this) {
            self::Disabled => 'Desativada',
            self::Polling => 'Consulta periódica',
            self::Realtime => 'Eventos em tempo real',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Disabled => 'O VANGUARD não consulta nem recebe eventos deste dispositivo.',
            self::Polling => 'O VANGUARD consulta periodicamente eventos já registrados no equipamento.',
            self::Realtime => 'O VANGUARD recebe os eventos enviados pelo equipamento em tempo real.',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $mode): array => [
                $mode->value => $mode->label(),
            ])
            ->all();
    }
}
