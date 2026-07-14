<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessDeviceRecords\Schemas;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Operations\Application\AccessControl\AccessControlRuntime;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventCollectionMode;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Support\VanguardText;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class AccessDeviceRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                Hidden::make('tenant_id')
                    ->default(
                        fn (): ?string => app(TenantContext::class)
                            ->currentTenantIdForUser(auth()->user())
                    ),

                Hidden::make('device_type')
                    ->default('facial_reader'),

                Tabs::make('Cadastro do dispositivo')
                    ->id('access-device-record-form-tabs')
                    ->persistTab()
                    ->tabs([
                        Tab::make('Dispositivo')
                            ->schema([
                                Section::make('Identificação')
                                    ->description('Identificação física e operacional do leitor facial ou controlador.')
                                    ->columns(6)
                                    ->schema([
                                        Select::make('organization_id')
                                            ->label('Unidade')
                                            ->helperText('A unidade não poderá ser alterada depois que o dispositivo for cadastrado.')
                                            ->options(
                                                fn (): array => self::organizationOptions()
                                            )
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->native(false)
                                            ->disabled(
                                                fn (?AccessDeviceRecord $record): bool => $record !== null
                                            )
                                            ->dehydrated(true)
                                            ->columnSpan(3),

                                        TextInput::make('code')
                                            ->label('Código')
                                            ->placeholder('Ex: FAC-ENT-01')
                                            ->helperText('Código interno usado para identificar o equipamento.')
                                            ->required()
                                            ->maxLength(100)
                                            ->dehydrateStateUsing(
                                                fn (?string $state): ?string => filled($state)
                                                    ? trim($state)
                                                    : null
                                            )
                                            ->columnSpan(3),

                                        TextInput::make('name')
                                            ->label('Nome')
                                            ->placeholder('Ex: Facial entrada 01')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(3),

                                        Select::make('direction')
                                            ->label('Direção')
                                            ->options(
                                                AccessDeviceDirection::options()
                                            )
                                            ->required()
                                            ->native(false)
                                            ->columnSpan(2),

                                        Select::make('status')
                                            ->label('Status')
                                            ->options(
                                                AccessDeviceStatus::options()
                                            )
                                            ->default(
                                                AccessDeviceStatus::Active->value
                                            )
                                            ->required()
                                            ->native(false)
                                            ->columnSpan(1),

                                        Select::make('provider')
                                            ->label('Fabricante / provider')
                                            ->options([
                                                'intelbras' => 'Intelbras',
                                            ])
                                            ->default('intelbras')
                                            ->required()
                                            ->native(false)
                                            ->disabled(
                                                fn (?AccessDeviceRecord $record): bool => $record !== null
                                            )
                                            ->dehydrated(true)
                                            ->columnSpan(2),

                                        TextInput::make('model')
                                            ->label('Modelo')
                                            ->placeholder('Ex: SS 3532 MF W')
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        TextInput::make('serial_number')
                                            ->label('Número de série')
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        TextInput::make('external_id')
                                            ->label('Identificador externo')
                                            ->helperText('Identificador utilizado pela integração Intelbras, quando disponível.')
                                            ->maxLength(255)
                                            ->columnSpan(3),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Comunicação')
                            ->schema([
                                Section::make('Rede')
                                    ->description('Apenas configurações cadastrais. Nenhuma comunicação será executada neste bloco.')
                                    ->columns(6)
                                    ->schema([
                                        TextInput::make('ip_address')
                                            ->label('Endereço IP')
                                            ->placeholder('192.168.10.21')
                                            ->rule('ip')
                                            ->maxLength(45)
                                            ->columnSpan(2),

                                        TextInput::make('port')
                                            ->label('Porta')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(65535)
                                            ->default(80)
                                            ->columnSpan(1),

                                        Select::make('protocol')
                                            ->label('Protocolo')
                                            ->options([
                                                'http' => 'HTTP',
                                                'https' => 'HTTPS',
                                            ])
                                            ->default('http')
                                            ->required()
                                            ->native(false)
                                            ->columnSpan(1),

                                        Select::make('auth_type')
                                            ->label('Autenticação')
                                            ->options([
                                                'digest' => 'HTTP Digest',
                                            ])
                                            ->default('digest')
                                            ->required()
                                            ->native(false)
                                            ->columnSpan(2),
                                    ])
                                    ->columnSpanFull(),

                                Section::make('Credenciais')
                                    ->description('As credenciais são criptografadas. Na edição, deixe os campos vazios para manter os valores atuais.')
                                    ->columns(6)
                                    ->schema([
                                        TextInput::make(
                                            'credential_username'
                                        )
                                            ->label('Usuário')
                                            ->password()
                                            ->revealable()
                                            ->afterStateHydrated(
                                                function (
                                                    TextInput $component
                                                ): void {
                                                    $component->state(null);
                                                }
                                            )
                                            ->dehydrated(
                                                fn (?string $state): bool => filled($state)
                                            )
                                            ->maxLength(255)
                                            ->columnSpan(3),

                                        TextInput::make(
                                            'credential_password'
                                        )
                                            ->label('Senha')
                                            ->password()
                                            ->revealable()
                                            ->afterStateHydrated(
                                                function (
                                                    TextInput $component
                                                ): void {
                                                    $component->state(null);
                                                }
                                            )
                                            ->dehydrated(
                                                fn (?string $state): bool => filled($state)
                                            )
                                            ->maxLength(255)
                                            ->columnSpan(3),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Integração')
                            ->schema([
                                Section::make('Configuração local do VANGUARD')
                                    ->description('Define como o VANGUARD deverá consultar este dispositivo. Neste bloco, nenhuma comunicação será executada.')
                                    ->statePath('settings')
                                    ->columns(6)
                                    ->schema([
                                        Select::make('timezone')
                                            ->label('Fuso horário')
                                            ->options([
                                                'America/Sao_Paulo' => 'Brasília — America/Sao_Paulo',
                                                'America/Cuiaba' => 'Cuiabá — America/Cuiaba',
                                            ])
                                            ->default('America/Sao_Paulo')
                                            ->required()
                                            ->native(false)
                                            ->columnSpan(3),

                                        Select::make('event_collection_mode')
                                            ->label('Coleta de eventos')
                                            ->options(
                                                AccessEventCollectionMode::options()
                                            )
                                            ->default(
                                                AccessEventCollectionMode::Disabled->value
                                            )
                                            ->helperText('Permanece desativada até a integração ser homologada.')
                                            ->required()
                                            ->native(false)
                                            ->columnSpan(3),

                                        TextInput::make(
                                            'polling_interval_seconds'
                                        )
                                            ->label('Intervalo de consulta')
                                            ->helperText('Intervalo em segundos para consultas periódicas. Ainda não utilizado no modo atual.')
                                            ->numeric()
                                            ->default(30)
                                            ->minValue(10)
                                            ->maxValue(3600)
                                            ->suffix('segundos')
                                            ->columnSpan(2),

                                        TextInput::make(
                                            'recovery_window_minutes'
                                        )
                                            ->label('Janela de recuperação')
                                            ->helperText('Período anterior consultado para recuperar eventos após uma interrupção.')
                                            ->numeric()
                                            ->default(5)
                                            ->minValue(1)
                                            ->maxValue(1440)
                                            ->suffix('minutos')
                                            ->columnSpan(2),

                                        TextInput::make(
                                            'clock_tolerance_seconds'
                                        )
                                            ->label('Tolerância do relógio')
                                            ->helperText('Diferença máxima aceita entre o relógio do VANGUARD e o equipamento.')
                                            ->numeric()
                                            ->default(60)
                                            ->minValue(0)
                                            ->maxValue(3600)
                                            ->suffix('segundos')
                                            ->columnSpan(2),

                                        Toggle::make('verify_tls')
                                            ->label('Verificar certificado HTTPS')
                                            ->helperText('Aplicável somente quando o protocolo HTTPS estiver selecionado.')
                                            ->default(false)
                                            ->columnSpan(3),
                                    ])
                                    ->columnSpanFull(),

                            ]),

                        Tab::make('Configurações do equipamento')
                            ->schema(
                                AccessDeviceConfigurationSchema::formSections()
                            ),

                        Tab::make('Monitoramento')
                            ->schema([
                                Section::make('Modo operacional')
                                    ->description('Proteções globais aplicadas à integração.')
                                    ->columns(6)
                                    ->schema([
                                        Placeholder::make(
                                            'access_control_mode'
                                        )
                                            ->label('Modo atual')
                                            ->content(
                                                fn (): string => app(
                                                    AccessControlRuntime::class
                                                )->summary()
                                            )
                                            ->columnSpan(4),

                                        Placeholder::make(
                                            'access_control_writes'
                                        )
                                            ->label('Escrita nos equipamentos')
                                            ->content(
                                                fn (): string => app(
                                                    AccessControlRuntime::class
                                                )->allowsWrites()
                                                    ? 'Habilitada'
                                                    : 'Bloqueada'
                                            )
                                            ->columnSpan(2),
                                    ])
                                    ->columnSpanFull(),

                                Section::make('Última comunicação')
                                    ->columns(6)
                                    ->schema([
                                        Placeholder::make(
                                            'last_communication_status_display'
                                        )
                                            ->label('Resultado')
                                            ->content(
                                                fn (
                                                    ?AccessDeviceRecord $record
                                                ): string => VanguardText::upper(
                                                    $record?->last_communication_status
                                                        ?: 'Ainda não testado'
                                                )
                                            )
                                            ->columnSpan(2),

                                        Placeholder::make(
                                            'last_communication_at_display'
                                        )
                                            ->label('Data e hora')
                                            ->content(
                                                fn (
                                                    ?AccessDeviceRecord $record
                                                ): string => $record?->last_communication_at
                                                    ?->format('d/m/Y H:i:s')
                                                    ?: '-'
                                            )
                                            ->columnSpan(2),

                                        Placeholder::make(
                                            'last_event_at_display'
                                        )
                                            ->label('Último evento')
                                            ->content(
                                                fn (
                                                    ?AccessDeviceRecord $record
                                                ): string => $record?->last_event_at
                                                    ?->format('d/m/Y H:i:s')
                                                    ?: '-'
                                            )
                                            ->columnSpan(2),

                                        Placeholder::make(
                                            'last_communication_message_display'
                                        )
                                            ->label('Mensagem')
                                            ->content(
                                                fn (
                                                    ?AccessDeviceRecord $record
                                                ): string => $record?->last_communication_message
                                                    ?: '-'
                                            )
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Observações')
                            ->schema([
                                Section::make('Observações')
                                    ->schema([
                                        Textarea::make('notes')
                                            ->label('Observações técnicas')
                                            ->rows(6)
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function organizationOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $query = OrganizationRecord::query()
            ->where('status', 'active')
            ->orderBy('unit_code')
            ->orderBy('display_name')
            ->orderBy('trade_name')
            ->orderBy('legal_name');

        app(TenantContext::class)->applyOrganizationScope(
            $query,
            $user
        );

        app(TenantContext::class)->applyUserOrganizationScope(
            $query,
            $user,
            'id'
        );

        return $query
            ->get()
            ->mapWithKeys(
                fn (
                    OrganizationRecord $organization
                ): array => [
                    $organization->id => VanguardText::upper(
                        collect([
                            $organization->unit_code,
                            $organization->operational_name,
                        ])
                            ->filter()
                            ->implode(' - ')
                    ),
                ]
            )
            ->all();
    }
}
