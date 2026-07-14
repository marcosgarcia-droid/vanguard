<?php

namespace App\Modules\Operations\Domain\AccessControl;

enum AccessControlMode: string
{
    case Observer = 'observer';
    case Parallel = 'parallel';
    case Primary = 'primary';
    case Emergency = 'emergency';

    public function label(): string
    {
        return match ($this) {
            self::Observer => 'Observador',
            self::Parallel => 'Operação paralela',
            self::Primary => 'VANGUARD principal',
            self::Emergency => 'Contingência Intelbras',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Observer => 'Somente leitura. O software Intelbras continua responsável pela operação.',
            self::Parallel => 'O VANGUARD compara dados e decisões sem controlar os equipamentos.',
            self::Primary => 'O VANGUARD é a fonte principal de cadastros, regras e decisões de acesso.',
            self::Emergency => 'Operação temporária de contingência pelo ambiente Intelbras.',
        };
    }

    public function canWriteToDevices(): bool
    {
        return in_array($this, [
            self::Primary,
            self::Emergency,
        ], true);
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
