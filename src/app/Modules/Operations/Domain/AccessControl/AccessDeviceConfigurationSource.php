<?php

namespace App\Modules\Operations\Domain\AccessControl;

enum AccessDeviceConfigurationSource: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';
    case Imported = 'imported';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Consulta manual',
            self::Scheduled => 'Consulta programada',
            self::Imported => 'Importação',
        };
    }
}
