<?php

namespace App\Modules\Operations\Domain\AccessControl;

enum AccessDeviceCapabilityStatus: string
{
    case Supported = 'supported';
    case Unsupported = 'unsupported';
    case FirmwareRequired = 'firmware_required';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Supported => 'Suportado',
            self::Unsupported => 'Não suportado',
            self::FirmwareRequired => 'Requer firmware compatível',
            self::Unknown => 'Aguardando leitura do equipamento',
        };
    }
}
