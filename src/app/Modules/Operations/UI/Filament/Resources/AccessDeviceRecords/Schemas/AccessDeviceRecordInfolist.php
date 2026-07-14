<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessDeviceRecords\Schemas;

use App\Modules\Operations\Application\AccessControl\AccessControlRuntime;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventCollectionMode;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Support\VanguardText;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class AccessDeviceRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                Tabs::make('Visualização do dispositivo')
                    ->id('access-device-record-infolist-tabs')
                    ->persistTab()
                    ->tabs([
                        Tab::make('Dispositivo')
                            ->schema([
                                Section::make('Identificação')
                                    ->columns(6)
                                    ->schema([
                                        TextEntry::make('code')
                                            ->label('Código')
                                            ->formatStateUsing(
                                                fn (?string $state): string => VanguardText::upper(
                                                    $state
                                                )
                                            )
                                            ->columnSpan(2),

                                        TextEntry::make('name')
                                            ->label('Nome')
                                            ->formatStateUsing(
                                                fn (?string $state): string => VanguardText::upper(
                                                    $state
                                                )
                                            )
                                            ->columnSpan(4),

                                        TextEntry::make(
                                            'tenant.name'
                                        )
                                            ->label('Grupo empresarial')
                                            ->formatStateUsing(
                                                fn (?string $state): string => VanguardText::upper(
                                                    $state
                                                )
                                            )
                                            ->columnSpan(3),

                                        TextEntry::make(
                                            'organization.display_name'
                                        )
                                            ->label('Unidade')
                                            ->state(
                                                fn (
                                                    AccessDeviceRecord $record
                                                ): string => VanguardText::upper(
                                                    $record->organization
                                                        ?->operational_name
                                                )
                                            )
                                            ->columnSpan(3),

                                        TextEntry::make('provider')
                                            ->label('Fabricante / provider')
                                            ->formatStateUsing(
                                                fn (?string $state): string => VanguardText::upper(
                                                    $state
                                                )
                                            )
                                            ->columnSpan(2),

                                        TextEntry::make('model')
                                            ->label('Modelo')
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make(
                                            'serial_number'
                                        )
                                            ->label('Número de série')
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('external_id')
                                            ->label('Identificador externo')
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('direction')
                                            ->label('Direção')
                                            ->badge()
                                            ->formatStateUsing(
                                                fn (
                                                    mixed $state
                                                ): string => self::directionLabel(
                                                    $state
                                                )
                                            )
                                            ->columnSpan(2),

                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->formatStateUsing(
                                                fn (
                                                    mixed $state
                                                ): string => self::statusLabel(
                                                    $state
                                                )
                                            )
                                            ->columnSpan(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Comunicação')
                            ->schema([
                                Section::make('Rede')
                                    ->columns(6)
                                    ->schema([
                                        TextEntry::make('ip_address')
                                            ->label('Endereço IP')
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('port')
                                            ->label('Porta')
                                            ->placeholder('-')
                                            ->columnSpan(1),

                                        TextEntry::make('protocol')
                                            ->label('Protocolo')
                                            ->formatStateUsing(
                                                fn (?string $state): string => strtoupper(
                                                    $state ?: '-'
                                                )
                                            )
                                            ->columnSpan(1),

                                        TextEntry::make('auth_type')
                                            ->label('Autenticação')
                                            ->formatStateUsing(
                                                fn (?string $state): string => $state === 'digest'
                                                    ? 'HTTP Digest'
                                                    : VanguardText::upper(
                                                        $state
                                                    )
                                            )
                                            ->columnSpan(2),

                                        TextEntry::make(
                                            'credentials_status'
                                        )
                                            ->label('Credenciais')
                                            ->state(
                                                fn (
                                                    AccessDeviceRecord $record
                                                ): string => $record
                                                    ->hasConfiguredCredentials()
                                                        ? 'Configuradas'
                                                        : 'Não configuradas'
                                            )
                                            ->badge()
                                            ->color(
                                                fn (
                                                    AccessDeviceRecord $record
                                                ): string => $record
                                                    ->hasConfiguredCredentials()
                                                        ? 'success'
                                                        : 'gray'
                                            )
                                            ->columnSpan(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Integração')
                            ->schema([
                                Section::make('Configuração local do VANGUARD')
                                    ->columns(6)
                                    ->schema([
                                        TextEntry::make(
                                            'settings.timezone'
                                        )
                                            ->label('Fuso horário')
                                            ->placeholder('-')
                                            ->columnSpan(3),

                                        TextEntry::make(
                                            'settings.event_collection_mode'
                                        )
                                            ->label('Coleta de eventos')
                                            ->state(
                                                function (
                                                    AccessDeviceRecord $record
                                                ): string {
                                                    $mode =
                                                        AccessEventCollectionMode::tryFrom(
                                                            (string) data_get(
                                                                $record->settings,
                                                                'event_collection_mode',
                                                                AccessEventCollectionMode::Disabled->value
                                                            )
                                                        );

                                                    return $mode?->label()
                                                        ?? AccessEventCollectionMode::Disabled->label();
                                                }
                                            )
                                            ->badge()
                                            ->columnSpan(3),

                                        TextEntry::make(
                                            'settings.polling_interval_seconds'
                                        )
                                            ->label('Intervalo de consulta')
                                            ->state(
                                                fn (
                                                    AccessDeviceRecord $record
                                                ): string => data_get(
                                                    $record->settings,
                                                    'polling_interval_seconds',
                                                    30
                                                ).' segundos'
                                            )
                                            ->columnSpan(2),

                                        TextEntry::make(
                                            'settings.recovery_window_minutes'
                                        )
                                            ->label('Janela de recuperação')
                                            ->state(
                                                fn (
                                                    AccessDeviceRecord $record
                                                ): string => data_get(
                                                    $record->settings,
                                                    'recovery_window_minutes',
                                                    5
                                                ).' minutos'
                                            )
                                            ->columnSpan(2),

                                        TextEntry::make(
                                            'settings.clock_tolerance_seconds'
                                        )
                                            ->label('Tolerância do relógio')
                                            ->state(
                                                fn (
                                                    AccessDeviceRecord $record
                                                ): string => data_get(
                                                    $record->settings,
                                                    'clock_tolerance_seconds',
                                                    60
                                                ).' segundos'
                                            )
                                            ->columnSpan(2),

                                        TextEntry::make(
                                            'settings.verify_tls'
                                        )
                                            ->label('Verificar certificado HTTPS')
                                            ->state(
                                                fn (
                                                    AccessDeviceRecord $record
                                                ): string => data_get(
                                                    $record->settings,
                                                    'verify_tls',
                                                    false
                                                )
                                                    ? 'Sim'
                                                    : 'Não'
                                            )
                                            ->badge()
                                            ->columnSpan(2),
                                    ])
                                    ->columnSpanFull(),

                            ]),

                        Tab::make('Configurações do equipamento')
                            ->schema(
                                AccessDeviceConfigurationSchema::infolistSections()
                            ),

                        Tab::make('Monitoramento')
                            ->schema([
                                Section::make('Modo operacional')
                                    ->columns(6)
                                    ->schema([
                                        TextEntry::make(
                                            'operational_mode'
                                        )
                                            ->label('Modo atual')
                                            ->state(
                                                fn (): string => app(
                                                    AccessControlRuntime::class
                                                )->mode()->label()
                                            )
                                            ->badge()
                                            ->color('warning')
                                            ->columnSpan(2),

                                        TextEntry::make(
                                            'writes_status'
                                        )
                                            ->label('Escrita nos equipamentos')
                                            ->state(
                                                fn (): string => app(
                                                    AccessControlRuntime::class
                                                )->allowsWrites()
                                                    ? 'Habilitada'
                                                    : 'Bloqueada'
                                            )
                                            ->badge()
                                            ->color(
                                                fn (): string => app(
                                                    AccessControlRuntime::class
                                                )->allowsWrites()
                                                    ? 'danger'
                                                    : 'success'
                                            )
                                            ->columnSpan(2),

                                        TextEntry::make(
                                            'mode_description'
                                        )
                                            ->label('Descrição')
                                            ->state(
                                                fn (): string => app(
                                                    AccessControlRuntime::class
                                                )->mode()->description()
                                            )
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),

                                Section::make('Comunicação e eventos')
                                    ->columns(6)
                                    ->schema([
                                        TextEntry::make(
                                            'last_communication_status'
                                        )
                                            ->label('Último resultado')
                                            ->placeholder(
                                                'Ainda não testado'
                                            )
                                            ->columnSpan(2),

                                        TextEntry::make(
                                            'last_communication_at'
                                        )
                                            ->label('Última comunicação')
                                            ->dateTime('d/m/Y H:i:s')
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make(
                                            'last_event_at'
                                        )
                                            ->label('Último evento')
                                            ->dateTime('d/m/Y H:i:s')
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make(
                                            'last_communication_message'
                                        )
                                            ->label('Mensagem')
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Observações')
                            ->schema([
                                Section::make('Observações')
                                    ->schema([
                                        TextEntry::make('notes')
                                            ->label('Observações técnicas')
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function directionLabel(mixed $state): string
    {
        $direction = $state instanceof AccessDeviceDirection
            ? $state
            : AccessDeviceDirection::tryFrom((string) $state);

        return $direction?->label() ?: '-';
    }

    private static function statusLabel(mixed $state): string
    {
        $status = $state instanceof AccessDeviceStatus
            ? $state
            : AccessDeviceStatus::tryFrom((string) $state);

        return $status?->label() ?: '-';
    }
}
