<?php

namespace App\Modules\Operations\Domain\AccessControl;

enum AccessDeviceConfigurationReadStatus: string
{
    case Success = 'success';
    case Partial = 'partial';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Leitura concluída',
            self::Partial => 'Leitura parcial',
            self::Failed => 'Falha na leitura',
        };
    }
}
