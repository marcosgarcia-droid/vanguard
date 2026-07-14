<?php

namespace App\Modules\Operations\Domain\AccessControl;

enum AccessDeviceConfigurationOperation: string
{
    case Configuration = 'configuration';
    case Status = 'status';
    case Command = 'command';

    public function label(): string
    {
        return match ($this) {
            self::Configuration => 'Parâmetro configurável',
            self::Status => 'Informação ou estado',
            self::Command => 'Comando operacional',
        };
    }
}
