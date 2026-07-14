<?php

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras;

enum IntelbrasReadOnlyEndpoint: string
{
    case CurrentTime = 'current_time';
    case SoftwareVersion = 'software_version';
    case AccessControlGeneral = 'access_control_general';
    case AccessControl = 'access_control';
    case DoorStatus = 'door_status';

    public function label(): string
    {
        return match ($this) {
            self::CurrentTime => 'Data e hora',
            self::SoftwareVersion => 'Versão do firmware',
            self::AccessControlGeneral => 'Configurações gerais de acesso',
            self::AccessControl => 'Configurações de porta e relé',
            self::DoorStatus => 'Status da porta',
        };
    }

    public function path(): string
    {
        return match ($this) {
            self::CurrentTime => '/cgi-bin/global.cgi',
            self::SoftwareVersion => '/cgi-bin/magicBox.cgi',
            self::AccessControlGeneral,
            self::AccessControl => '/cgi-bin/configManager.cgi',
            self::DoorStatus => '/cgi-bin/accessControl.cgi',
        };
    }

    /**
     * @return array<string, string|int>
     */
    public function query(): array
    {
        return match ($this) {
            self::CurrentTime => [
                'action' => 'getCurrentTime',
            ],
            self::SoftwareVersion => [
                'action' => 'getSoftwareVersion',
            ],
            self::AccessControlGeneral => [
                'action' => 'getConfig',
                'name' => 'AccessControlGeneral',
            ],
            self::AccessControl => [
                'action' => 'getConfig',
                'name' => 'AccessControl',
            ],
            self::DoorStatus => [
                'action' => 'getDoorStatus',
                'channel' => 1,
            ],
        };
    }

    public function isEssential(): bool
    {
        return $this === self::CurrentTime;
    }

    public function relativeUrl(): string
    {
        return $this->path()
            .'?'
            .http_build_query(
                $this->query(),
                '',
                '&',
                PHP_QUERY_RFC3986
            );
    }
}
