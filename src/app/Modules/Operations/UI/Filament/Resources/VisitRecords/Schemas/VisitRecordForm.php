<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitRecords\Schemas;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorContactRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorDocumentRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class VisitRecordForm
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

                Hidden::make('status')
                    ->default(VisitStatus::Scheduled->value)
                    ->required(),

                Tabs::make('Agendamento da visita')
                    ->id('visit-record-form-tabs')
                    ->persistTab()
                    ->tabs([
                        Tab::make('Visita')
                            ->schema([
                                Section::make('Dados da visita')
                                    ->description('Informe a unidade, o visitante e a finalidade do acesso.')
                                    ->columns(6)
                                    ->schema([
                                        Select::make('organization_id')
                                            ->label('Unidade')
                                            ->options(
                                                fn (): array => self::organizationOptions()
                                            )
                                            ->default(
                                                fn (): ?string => self::defaultOrganizationId()
                                            )
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(function ($set): void {
                                                $set('visitor_id', null);
                                                $set('host_employee_id', null);
                                                $set('partner_id', null);
                                            })
                                            ->native(false)
                                            ->columnSpan(3),

                                        Select::make('visitor_id')
                                            ->label('Visitante')
                                            ->helperText('São exibidos apenas visitantes ativos da unidade selecionada.')
                                            ->options(
                                                fn ($get): array => self::visitorOptions(
                                                    $get('organization_id')
                                                )
                                            )
                                            ->createOptionForm(
                                                function ($get): array {
                                                    $organizationId = $get(
                                                        'organization_id'
                                                    );

                                                    return [
                                                        Section::make(
                                                            'Cadastro rápido do visitante'
                                                        )
                                                            ->description(
                                                                'O visitante será vinculado automaticamente à unidade da visita.'
                                                            )
                                                            ->columns(6)
                                                            ->schema([
                                                                Hidden::make(
                                                                    'organization_id'
                                                                )
                                                                    ->default(
                                                                        $organizationId
                                                                    )
                                                                    ->required(),

                                                                TextInput::make(
                                                                    'full_name'
                                                                )
                                                                    ->label(
                                                                        'Nome completo'
                                                                    )
                                                                    ->required()
                                                                    ->maxLength(
                                                                        255
                                                                    )
                                                                    ->columnSpan(
                                                                        3
                                                                    ),

                                                                TextInput::make(
                                                                    'preferred_name'
                                                                )
                                                                    ->label(
                                                                        'Nome de uso'
                                                                    )
                                                                    ->maxLength(
                                                                        255
                                                                    )
                                                                    ->columnSpan(
                                                                        3
                                                                    ),

                                                                Select::make(
                                                                    'document_type'
                                                                )
                                                                    ->label(
                                                                        'Tipo de documento'
                                                                    )
                                                                    ->options([
                                                                        'cpf' => 'CPF',
                                                                        'rg' => 'RG',
                                                                        'cnh' => 'CNH',
                                                                        'passport' => 'Passaporte',
                                                                        'foreign_document' => 'Documento estrangeiro',
                                                                        'other' => 'Outro',
                                                                    ])
                                                                    ->default(
                                                                        'cpf'
                                                                    )
                                                                    ->required()
                                                                    ->native(
                                                                        false
                                                                    )
                                                                    ->columnSpan(
                                                                        2
                                                                    ),

                                                                TextInput::make(
                                                                    'document_number'
                                                                )
                                                                    ->label(
                                                                        'Número do documento'
                                                                    )
                                                                    ->helperText(
                                                                        'O número será salvo sem máscara quando aplicável.'
                                                                    )
                                                                    ->required()
                                                                    ->maxLength(
                                                                        255
                                                                    )
                                                                    ->columnSpan(
                                                                        4
                                                                    ),

                                                                Select::make(
                                                                    'contact_type'
                                                                )
                                                                    ->label(
                                                                        'Tipo de contato'
                                                                    )
                                                                    ->options([
                                                                        'mobile' => 'Celular',
                                                                        'whatsapp' => 'WhatsApp',
                                                                        'phone' => 'Telefone',
                                                                        'email' => 'E-mail',
                                                                    ])
                                                                    ->default(
                                                                        'mobile'
                                                                    )
                                                                    ->required()
                                                                    ->native(
                                                                        false
                                                                    )
                                                                    ->columnSpan(
                                                                        2
                                                                    ),

                                                                TextInput::make(
                                                                    'contact_value'
                                                                )
                                                                    ->label(
                                                                        'Contato'
                                                                    )
                                                                    ->required()
                                                                    ->maxLength(
                                                                        255
                                                                    )
                                                                    ->columnSpan(
                                                                        4
                                                                    ),

                                                                Select::make(
                                                                    'partner_id'
                                                                )
                                                                    ->label(
                                                                        'Parceiro / empresa representada'
                                                                    )
                                                                    ->options(
                                                                        self::partnerOptions(
                                                                            $organizationId
                                                                        )
                                                                    )
                                                                    ->searchable()
                                                                    ->preload()
                                                                    ->native(
                                                                        false
                                                                    )
                                                                    ->columnSpanFull(),
                                                            ]),
                                                    ];
                                                }
                                            )
                                            ->createOptionUsing(
                                                fn (
                                                    array $data,
                                                    $get
                                                ): string => self::createVisitorOption(
                                                    $data,
                                                    $get('organization_id')
                                                )
                                            )
                                            ->createOptionModalHeading(
                                                'Novo visitante'
                                            )
                                            ->createOptionAction(
                                                fn (
                                                    Action $action
                                                ): Action => $action
                                                    ->label(
                                                        'Novo visitante'
                                                    )
                                                    ->tooltip(
                                                        'Cadastrar novo visitante'
                                                    )
                                                    ->visible(
                                                        fn (): bool => auth()
                                                            ->user()
                                                            ?->can(
                                                                'Create:VisitorRecord'
                                                            ) ?? false
                                                    )
                                                    ->modalWidth(
                                                        Width::SevenExtraLarge
                                                    )
                                                    ->modalSubmitActionLabel(
                                                        'Cadastrar visitante'
                                                    )
                                            )
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(function ($state, $set): void {
                                                $partnerId = filled($state)
                                                    ? VisitorRecord::query()
                                                        ->whereKey($state)
                                                        ->value('partner_id')
                                                    : null;

                                                $set('partner_id', $partnerId);
                                            })
                                            ->native(false)
                                            ->columnSpan(3),

                                        Select::make('host_employee_id')
                                            ->label('Visitado')
                                            ->helperText('Funcionário responsável por receber o visitante.')
                                            ->options(
                                                fn ($get): array => self::employeeOptions(
                                                    $get('organization_id')
                                                )
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->native(false)
                                            ->columnSpan(3),

                                        Select::make('partner_id')
                                            ->label('Parceiro / empresa representada')
                                            ->options(
                                                fn ($get): array => self::partnerOptions(
                                                    $get('organization_id')
                                                )
                                            )
                                            ->createOptionForm(
                                                function ($get): array {
                                                    $organizationId = $get(
                                                        'organization_id'
                                                    );

                                                    return [
                                                        Section::make(
                                                            'Cadastro rápido do parceiro'
                                                        )
                                                            ->description(
                                                                'O parceiro será vinculado automaticamente à unidade da visita.'
                                                            )
                                                            ->columns(6)
                                                            ->schema([
                                                                Hidden::make(
                                                                    'organization_id'
                                                                )
                                                                    ->default(
                                                                        $organizationId
                                                                    )
                                                                    ->required(),

                                                                TextInput::make(
                                                                    'official_document'
                                                                )
                                                                    ->label(
                                                                        'CPF / CNPJ'
                                                                    )
                                                                    ->placeholder(
                                                                        '000.000.000-00 ou 00.000.000/0000-00'
                                                                    )
                                                                    ->helperText(
                                                                        'Para CNPJ, informe os 14 dígitos e use Buscar CNPJ para preencher os dados automaticamente.'
                                                                    )
                                                                    ->required()
                                                                    ->live(
                                                                        debounce: 700
                                                                    )
                                                                    ->dehydrateStateUsing(
                                                                        fn (
                                                                            ?string $state
                                                                        ): ?string => PartnerRecord::normalizeOfficialDocument(
                                                                            $state
                                                                        )
                                                                    )
                                                                    ->suffixAction(
                                                                        Action::make(
                                                                            'lookupPartnerCnpj'
                                                                        )
                                                                            ->label(
                                                                                'Buscar CNPJ'
                                                                            )
                                                                            ->tooltip(
                                                                                'Buscar dados do CNPJ'
                                                                            )
                                                                            ->icon(
                                                                                'heroicon-o-magnifying-glass'
                                                                            )
                                                                            ->button()
                                                                            ->action(
                                                                                fn (
                                                                                    $get,
                                                                                    $set
                                                                                ): null => self::lookupPartnerCnpj(
                                                                                    $get,
                                                                                    $set
                                                                                )
                                                                            ),
                                                                        isInline: true,
                                                                    )
                                                                    ->maxLength(
                                                                        18
                                                                    )
                                                                    ->columnSpan(
                                                                        3
                                                                    ),

                                                                TextInput::make(
                                                                    'name'
                                                                )
                                                                    ->label(
                                                                        'Nome / Razão social'
                                                                    )
                                                                    ->required()
                                                                    ->maxLength(
                                                                        255
                                                                    )
                                                                    ->columnSpan(
                                                                        3
                                                                    ),

                                                                TextInput::make(
                                                                    'trade_name'
                                                                )
                                                                    ->label(
                                                                        'Nome fantasia / Apelido'
                                                                    )
                                                                    ->maxLength(
                                                                        255
                                                                    )
                                                                    ->columnSpanFull(),
                                                            ]),
                                                    ];
                                                }
                                            )
                                            ->createOptionUsing(
                                                fn (
                                                    array $data,
                                                    $get
                                                ): string => self::createPartnerOption(
                                                    $data,
                                                    $get('organization_id')
                                                )
                                            )
                                            ->createOptionModalHeading(
                                                'Novo parceiro'
                                            )
                                            ->createOptionAction(
                                                fn (
                                                    Action $action
                                                ): Action => $action
                                                    ->label(
                                                        'Novo parceiro'
                                                    )
                                                    ->tooltip(
                                                        'Cadastrar novo parceiro'
                                                    )
                                                    ->visible(
                                                        fn (): bool => auth()
                                                            ->user()
                                                            ?->can(
                                                                'Create:PartnerRecord'
                                                            ) ?? false
                                                    )
                                                    ->modalWidth(
                                                        Width::SevenExtraLarge
                                                    )
                                                    ->modalSubmitActionLabel(
                                                        'Cadastrar parceiro'
                                                    )
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->native(false)
                                            ->columnSpan(3),

                                        TextInput::make('purpose')
                                            ->label('Finalidade da visita')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Data e horário')
                            ->schema([
                                Section::make('Previsão')
                                    ->description('O horário previsto orienta a portaria, mas não bloqueia exceções operacionais.')
                                    ->columns(4)
                                    ->schema([
                                        ToggleButtons::make('schedule_period')
                                            ->label('Período rápido')
                                            ->helperText('Selecione um período para preencher automaticamente o início e o término previstos.')
                                            ->options([
                                                'morning' => 'Manhã',
                                                'afternoon' => 'Tarde',
                                                'full_day' => 'Dia todo',
                                            ])
                                            ->icons([
                                                'morning' => 'heroicon-o-sun',
                                                'afternoon' => 'heroicon-o-clock',
                                                'full_day' => 'heroicon-o-calendar-days',
                                            ])
                                            ->grouped()
                                            ->inline()
                                            ->live()
                                            ->dehydrated(false)
                                            ->afterStateUpdated(
                                                function ($state, $get, $set): void {
                                                    self::applySchedulePeriod(
                                                        $state,
                                                        $get('expected_start_at'),
                                                        $set
                                                    );
                                                }
                                            )
                                            ->columnSpanFull(),

                                        DateTimePicker::make('expected_start_at')
                                            ->label('Início previsto')
                                            ->seconds(false)
                                            ->default(now()->addHour())
                                            ->live()
                                            ->afterStateUpdated(
                                                fn ($get, $set): mixed => $set(
                                                    'schedule_period',
                                                    self::matchingSchedulePeriod(
                                                        $get('expected_start_at'),
                                                        $get('expected_end_at')
                                                    )
                                                )
                                            )
                                            ->required()
                                            ->columnSpan(2),

                                        DateTimePicker::make('expected_end_at')
                                            ->label('Término previsto')
                                            ->seconds(false)
                                            ->live()
                                            ->afterStateUpdated(
                                                fn ($get, $set): mixed => $set(
                                                    'schedule_period',
                                                    self::matchingSchedulePeriod(
                                                        $get('expected_start_at'),
                                                        $get('expected_end_at')
                                                    )
                                                )
                                            )
                                            ->afterOrEqual('expected_start_at')
                                            ->columnSpan(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Veículo')
                            ->schema([
                                Section::make('Veículo do visitante')
                                    ->description(
                                        'Preencha somente quando o visitante utilizar veículo para acessar a unidade.'
                                    )
                                    ->columns(6)
                                    ->schema([
                                        TextInput::make('vehicle_plate')
                                            ->label('Placa')
                                            ->placeholder('ABC1D23')
                                            ->helperText(
                                                'Aceita placa padrão antigo ou Mercosul.'
                                            )
                                            ->maxLength(8)
                                            ->columnSpan(2),

                                        TextInput::make('vehicle_brand')
                                            ->label('Marca')
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        TextInput::make('vehicle_model')
                                            ->label('Modelo')
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        TextInput::make('vehicle_color')
                                            ->label('Cor')
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        Toggle::make(
                                            'vehicle_entry_authorized'
                                        )
                                            ->label(
                                                'Autorizar entrada do veículo'
                                            )
                                            ->helperText(
                                                'Somente Gestor pode conceder esta autorização.'
                                            )
                                            ->default(false)
                                            ->visible(
                                                fn (): bool => auth()
                                                    ->user()
                                                    ?->can(
                                                        'AuthorizeVehicleEntry:VisitRecord'
                                                    ) ?? false
                                            )
                                            ->columnSpan(4),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Observações')
                            ->schema([
                                Section::make('Observações')
                                    ->schema([
                                        Textarea::make('notes')
                                            ->label('Observações')
                                            ->rows(5)
                                            ->maxLength(5000)
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function lookupPartnerCnpj(
        mixed $get,
        mixed $set
    ): null {
        $cnpj = preg_replace(
            '/\D+/',
            '',
            (string) $get('official_document')
        );

        if (strlen((string) $cnpj) !== 14) {
            Notification::make()
                ->title('Informe um CNPJ válido')
                ->body(
                    'Digite os 14 números do CNPJ antes de buscar.'
                )
                ->danger()
                ->send();

            return null;
        }

        try {
            $cnpjValue = new Cnpj((string) $cnpj);
        } catch (Throwable) {
            Notification::make()
                ->title('CNPJ inválido')
                ->body(
                    'Verifique os números informados antes de realizar a consulta.'
                )
                ->danger()
                ->send();

            return null;
        }

        $organization = self::organizationForQuickCreation(
            $get('organization_id')
        );

        if (
            PartnerRecord::officialDocumentExistsForTenant(
                $organization->tenant_id,
                $cnpj
            )
        ) {
            Notification::make()
                ->title('Parceiro já cadastrado')
                ->body(
                    'Este CNPJ já está vinculado a um parceiro deste grupo empresarial.'
                )
                ->danger()
                ->send();

            return null;
        }

        try {
            $result = app(CnpjLookupProvider::class)
                ->lookup($cnpjValue);

            $payload = is_array(
                $result->normalizedPayload ?? null
            )
                ? $result->normalizedPayload
                : [];

            $legalName = self::firstFilledCnpjValue([
                $result->legalName ?? null,
                data_get($payload, 'legal_name'),
                data_get($payload, 'company.legal_name'),
                data_get($payload, 'name'),
                data_get($payload, 'razao_social'),
            ]);

            $tradeName = self::firstFilledCnpjValue([
                $result->tradeName ?? null,
                data_get($payload, 'trade_name'),
                data_get($payload, 'company.trade_name'),
                data_get($payload, 'fantasy_name'),
                data_get($payload, 'nome_fantasia'),
            ]);

            $set(
                'official_document',
                self::formatPartnerCnpj((string) $cnpj)
            );

            if (filled($legalName)) {
                $set('name', $legalName);
            }

            if (filled($tradeName)) {
                $set('trade_name', $tradeName);
            }

            Notification::make()
                ->title('Consulta CNPJ concluída')
                ->body(
                    filled($tradeName)
                        ? 'Razão social e nome fantasia foram preenchidos automaticamente.'
                        : 'A razão social foi preenchida. O nome fantasia não foi informado pela consulta.'
                )
                ->success()
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Consulta CNPJ indisponível')
                ->body(
                    'Não conseguimos consultar os serviços de CNPJ agora. Isso pode acontecer por instabilidade ou limite temporário das APIs gratuitas. Tente novamente mais tarde.'
                )
                ->warning()
                ->send();
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private static function firstFilledCnpjValue(
        array $values
    ): ?string {
        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private static function formatPartnerCnpj(
        string $cnpj
    ): string {
        return preg_replace(
            '/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/',
            '$1.$2.$3/$4-$5',
            $cnpj
        ) ?: $cnpj;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function createPartnerOption(
        array $data,
        mixed $organizationId
    ): string {
        $user = auth()->user();

        if (
            ! $user
            || ! $user->can('Create:PartnerRecord')
        ) {
            throw ValidationException::withMessages([
                'official_document' => 'Você não possui permissão para cadastrar parceiros.',
            ]);
        }

        $organization = self::organizationForQuickCreation(
            filled($organizationId)
                ? (string) $organizationId
                : ($data['organization_id'] ?? null)
        );

        $officialDocument = PartnerRecord::normalizeOfficialDocument(
            $data['official_document'] ?? null
        );

        $personType = PartnerRecord::personTypeFromOfficialDocument(
            $officialDocument
        );

        if (! $officialDocument || ! $personType) {
            throw ValidationException::withMessages([
                'official_document' => 'Informe um CPF com 11 dígitos ou um CNPJ com 14 dígitos.',
            ]);
        }

        if (
            PartnerRecord::officialDocumentExistsForTenant(
                $organization->tenant_id,
                $officialDocument
            )
        ) {
            throw ValidationException::withMessages([
                'official_document' => 'Já existe um parceiro cadastrado com este CPF/CNPJ neste grupo empresarial.',
            ]);
        }

        return DB::transaction(
            function () use (
                $data,
                $organization,
                $officialDocument,
                $personType
            ): string {
                $partner = PartnerRecord::query()->create([
                    'tenant_id' => $organization->tenant_id,
                    'organization_id' => $organization->id,
                    'person_type' => $personType,
                    'name' => trim(
                        (string) $data['name']
                    ),
                    'trade_name' => filled(
                        $data['trade_name'] ?? null
                    )
                        ? trim(
                            (string) $data['trade_name']
                        )
                        : null,
                    'status' => 'active',
                    'profiles' => [],
                ]);

                $partner->syncOfficialDocument(
                    $officialDocument
                );

                return (string) $partner->id;
            }
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function createVisitorOption(
        array $data,
        mixed $organizationId
    ): string {
        $user = auth()->user();

        if (
            ! $user
            || ! $user->can('Create:VisitorRecord')
        ) {
            throw ValidationException::withMessages([
                'full_name' => 'Você não possui permissão para cadastrar visitantes.',
            ]);
        }

        $organization = self::organizationForQuickCreation(
            filled($organizationId)
                ? (string) $organizationId
                : ($data['organization_id'] ?? null)
        );

        $partnerId = filled($data['partner_id'] ?? null)
            ? (string) $data['partner_id']
            : null;

        if (filled($partnerId)) {
            $partnerExists = PartnerRecord::query()
                ->whereKey($partnerId)
                ->where('tenant_id', $organization->tenant_id)
                ->where('organization_id', $organization->id)
                ->where('status', 'active')
                ->exists();

            if (! $partnerExists) {
                throw ValidationException::withMessages([
                    'partner_id' => 'O parceiro selecionado não está disponível para esta unidade.',
                ]);
            }
        }

        $documentType = (string) (
            $data['document_type'] ?? ''
        );

        $documentNumber = (string) (
            $data['document_number'] ?? ''
        );

        if (
            VisitorRecord::documentExistsForOrganization(
                $organization->tenant_id,
                $organization->id,
                $documentType,
                $documentNumber
            )
        ) {
            throw ValidationException::withMessages([
                'document_number' => 'Já existe um visitante com este documento nesta unidade.',
            ]);
        }

        return DB::transaction(
            function () use (
                $data,
                $organization,
                $partnerId,
                $documentType,
                $documentNumber
            ): string {
                $visitor = VisitorRecord::query()->create([
                    'tenant_id' => $organization->tenant_id,
                    'organization_id' => $organization->id,
                    'partner_id' => $partnerId,
                    'full_name' => trim(
                        (string) $data['full_name']
                    ),
                    'preferred_name' => filled(
                        $data['preferred_name'] ?? null
                    )
                        ? trim(
                            (string) $data['preferred_name']
                        )
                        : null,
                    'photo_disk' => 'local',
                    'status' => VisitorStatus::Active->value,
                ]);

                VisitorDocumentRecord::query()->create([
                    'visitor_id' => $visitor->id,
                    'type' => $documentType,
                    'number' => $documentNumber,
                    'is_primary' => true,
                ]);

                VisitorContactRecord::query()->create([
                    'visitor_id' => $visitor->id,
                    'type' => (string) $data['contact_type'],
                    'value' => (string) $data['contact_value'],
                    'is_primary' => true,
                ]);

                return (string) $visitor->id;
            }
        );
    }

    private static function organizationForQuickCreation(
        mixed $organizationId
    ): OrganizationRecord {
        if (blank($organizationId)) {
            throw ValidationException::withMessages([
                'organization_id' => 'Selecione a unidade da visita antes de cadastrar o visitante.',
            ]);
        }

        $organization = OrganizationRecord::query()
            ->whereKey((string) $organizationId)
            ->where('status', 'active')
            ->first();

        if (! $organization instanceof OrganizationRecord) {
            throw ValidationException::withMessages([
                'organization_id' => 'A unidade selecionada não está disponível.',
            ]);
        }

        $tenantContext = app(TenantContext::class);
        $user = auth()->user();

        if (
            ! $tenantContext->hasOrganizationAccess(
                $user,
                $organization->id
            )
        ) {
            throw ValidationException::withMessages([
                'organization_id' => 'Você não possui acesso à unidade selecionada.',
            ]);
        }

        $currentTenantId = $tenantContext
            ->currentTenantIdForUser($user);

        if (
            filled($currentTenantId)
            && $currentTenantId !== $organization->tenant_id
        ) {
            throw ValidationException::withMessages([
                'organization_id' => 'A unidade não pertence ao grupo empresarial selecionado.',
            ]);
        }

        return $organization;
    }

    private static function defaultOrganizationId(): ?string
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $tenantContext = app(TenantContext::class);
        $currentTenantId = $tenantContext->currentTenantIdForUser($user);

        $employeeOrganizationId = EmployeeRecord::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->when(
                filled($currentTenantId),
                fn ($query) => $query->where(
                    'tenant_id',
                    $currentTenantId
                )
            )
            ->whereHas(
                'organization',
                fn ($query) => $query->where('status', 'active')
            )
            ->orderBy('full_name')
            ->value('organization_id');

        if (
            filled($employeeOrganizationId)
            && $tenantContext->hasOrganizationAccess(
                $user,
                $employeeOrganizationId
            )
        ) {
            return (string) $employeeOrganizationId;
        }

        $organizationIds = array_keys(self::organizationOptions());

        return count($organizationIds) === 1
            ? (string) $organizationIds[0]
            : null;
    }

    private static function applySchedulePeriod(
        mixed $period,
        mixed $currentStart,
        $set
    ): void {
        if (! in_array(
            $period,
            ['morning', 'afternoon', 'full_day'],
            true
        )) {
            return;
        }

        try {
            $baseDate = filled($currentStart)
                ? Carbon::parse($currentStart)
                : now();
        } catch (Throwable) {
            $baseDate = now();
        }

        [$startHour, $endHour] = match ($period) {
            'morning' => [8, 12],
            'afternoon' => [12, 18],
            'full_day' => [8, 18],
        };

        $set(
            'expected_start_at',
            $baseDate->copy()
                ->startOfDay()
                ->setTime($startHour, 0)
        );

        $set(
            'expected_end_at',
            $baseDate->copy()
                ->startOfDay()
                ->setTime($endHour, 0)
        );
    }

    private static function matchingSchedulePeriod(
        mixed $start,
        mixed $end
    ): ?string {
        if (blank($start) || blank($end)) {
            return null;
        }

        try {
            $startAt = Carbon::parse($start);
            $endAt = Carbon::parse($end);
        } catch (Throwable) {
            return null;
        }

        if (! $startAt->isSameDay($endAt)) {
            return null;
        }

        return match (
            $startAt->format('H:i').'|'.$endAt->format('H:i')
        ) {
            '08:00|12:00' => 'morning',
            '12:00|18:00' => 'afternoon',
            '08:00|18:00' => 'full_day',
            default => null,
        };
    }

    private static function organizationOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $tenantContext = app(TenantContext::class);

        $query = OrganizationRecord::query()
            ->where('status', 'active')
            ->orderBy('display_name');

        $tenantContext->applyTenantScope(
            $query,
            $user
        );

        $tenantContext->applyUserOrganizationScope(
            $query,
            $user
        );

        return $query
            ->get()
            ->mapWithKeys(fn (OrganizationRecord $organization): array => [
                $organization->id => $organization->operational_name,
            ])
            ->all();
    }

    private static function visitorOptions(
        ?string $organizationId
    ): array {
        if (blank($organizationId)) {
            return [];
        }

        return VisitorRecord::query()
            ->where('organization_id', $organizationId)
            ->where('status', VisitorStatus::Active)
            ->orderBy('full_name')
            ->pluck('full_name', 'id')
            ->all();
    }

    private static function employeeOptions(
        ?string $organizationId
    ): array {
        if (blank($organizationId)) {
            return [];
        }

        return EmployeeRecord::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->orderBy('full_name')
            ->pluck('full_name', 'id')
            ->all();
    }

    private static function partnerOptions(
        ?string $organizationId
    ): array {
        if (blank($organizationId)) {
            return [];
        }

        return PartnerRecord::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->get([
                'id',
                'name',
                'trade_name',
            ])
            ->sortBy(
                fn (PartnerRecord $partner): string => mb_strtolower(
                    $partner->display_name
                )
            )
            ->mapWithKeys(
                fn (PartnerRecord $partner): array => [
                    $partner->id => $partner->display_name,
                ]
            )
            ->all();
    }
}
