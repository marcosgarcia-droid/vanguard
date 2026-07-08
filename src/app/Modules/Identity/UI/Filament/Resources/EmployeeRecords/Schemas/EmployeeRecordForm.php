<?php

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Schemas;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleTemplateRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class EmployeeRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                Hidden::make('id')
                    ->default(fn (): string => (string) Str::uuid())
                    ->required(),

                Hidden::make('tenant_id')
                    ->default(fn (): ?string => app(TenantContext::class)->currentTenantIdForUser(auth()->user()))
                    ->required(),

                Hidden::make('photo_disk')
                    ->default('local')
                    ->required(),

                Tabs::make('Cadastro do funcionário')
                    ->id('employee-record-form-tabs')
                    ->persistTab()
                    ->tabs([
                        Tab::make('Funcionário')
                            ->schema([
                                Section::make('Dados principais')
                                    ->description('Dados principais do colaborador.')
                                    ->columns(6)
                                    ->schema([
                                        FileUpload::make('photo_path')
                                            ->label('Foto')
                                            ->helperText('Imagem privada do funcionário. Futuramente poderá ser processada para controle de acesso.')
                                            ->image()
                                            ->disk('local')
                                            ->directory('employees/photos')
                                            ->columnSpan(2),

                                        TextInput::make('employee_code')
                                            ->label('Matrícula')
                                            ->dehydrateStateUsing(fn (?string $state): ?string => self::clean($state))
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        Select::make('status')
                                            ->label('Status')
                                            ->required()
                                            ->default('active')
                                            ->options([
                                                'active' => 'Ativo',
                                                'inactive' => 'Inativo',
                                                'terminated' => 'Desligado',
                                            ])
                                            ->native(false)
                                            ->columnSpan(2),

                                        TextInput::make('full_name')
                                            ->label('Nome completo')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(3),

                                        TextInput::make('preferred_name')
                                            ->label('Nome de uso')
                                            ->maxLength(255)
                                            ->columnSpan(3),

                                        Select::make('gender')
                                            ->label('Sexo')
                                            ->options([
                                                'female' => 'Feminino',
                                                'male' => 'Masculino',
                                                'not_informed' => 'Não informado',
                                                'other' => 'Outro',
                                            ])
                                            ->native(false)
                                            ->columnSpan(2),

                                        DatePicker::make('birth_date')
                                            ->label('Data de nascimento')
                                            ->columnSpan(2),

                                        DatePicker::make('hired_at')
                                            ->label('Data de admissão')
                                            ->columnSpan(1),

                                        DatePicker::make('terminated_at')
                                            ->label('Data de desligamento')
                                            ->columnSpan(1),

                                        Select::make('employment_type')
                                            ->label('Tipo de vínculo')
                                            ->required()
                                            ->default('employee')
                                            ->options([
                                                'employee' => 'Funcionário',
                                                'contractor' => 'Prestador',
                                                'intern' => 'Estagiário',
                                                'temporary' => 'Temporário',
                                            ])
                                            ->native(false)
                                            ->columnSpan(2),

                                        TextInput::make('department')
                                            ->label('Departamento')
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        TextInput::make('position')
                                            ->label('Cargo')
                                            ->maxLength(255)
                                            ->columnSpan(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Vínculos')
                            ->schema([
                                Section::make('Vínculos')
                                    ->description('Relacionamentos internos do funcionário no Vanguard.')
                                    ->columns(6)
                                    ->schema([
                                        Select::make('organization_id')
                                            ->label('Unidade')
                                            ->options(fn (?EmployeeRecord $record): array => self::organizationOptions($record))
                                            ->searchable()
                                            ->preload()
                                            ->native(false)
                                            ->columnSpan(2),

                                        Select::make('manager_employee_id')
                                            ->label('Gestor responsável')
                                            ->options(fn (?EmployeeRecord $record): array => self::managerOptions($record))
                                            ->searchable()
                                            ->preload()
                                            ->native(false)
                                            ->columnSpan(2),

                                        Select::make('user_id')
                                            ->label('Usuário vinculado')
                                            ->helperText('Opcional. Use apenas quando o funcionário também acessa o Vanguard.')
                                            ->options(fn (?EmployeeRecord $record): array => self::userOptions($record))
                                            ->searchable()
                                            ->preload()
                                            ->native(false)
                                            ->columnSpan(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Documentos')
                            ->schema([
                                Section::make('Documentos')
                                    ->description('Documentos ficam armazenados sem máscara. A formatação é aplicada apenas na visualização.')
                                    ->schema([
                                        Repeater::make('documents')
                                            ->label('Documentos')
                                            ->relationship('documents')
                                            ->columns(6)
                                            ->schema([
                                                Select::make('type')
                                                    ->label('Tipo')
                                                    ->required()
                                                    ->options([
                                                        'cpf' => 'CPF',
                                                        'rg' => 'RG',
                                                        'cnh' => 'CNH',
                                                        'ctps' => 'CTPS',
                                                        'pis' => 'PIS/PASEP',
                                                        'voter_registration' => 'Título de eleitor',
                                                        'other' => 'Outro',
                                                    ])
                                                    ->native(false)
                                                    ->columnSpan(2),

                                                TextInput::make('number')
                                                    ->label('Número')
                                                    ->required()
                                                    ->dehydrateStateUsing(fn (?string $state): ?string => self::cleanDocument($state))
                                                    ->maxLength(255)
                                                    ->columnSpan(2),

                                                TextInput::make('issuing_authority')
                                                    ->label('Órgão emissor')
                                                    ->maxLength(255)
                                                    ->columnSpan(1),

                                                Select::make('is_primary')
                                                    ->label('Principal')
                                                    ->options([
                                                        1 => 'Sim',
                                                        0 => 'Não',
                                                    ])
                                                    ->default(0)
                                                    ->native(false)
                                                    ->columnSpan(1),

                                                DatePicker::make('issued_at')
                                                    ->label('Emissão')
                                                    ->columnSpan(1),

                                                DatePicker::make('expires_at')
                                                    ->label('Validade')
                                                    ->columnSpan(1),

                                                Textarea::make('notes')
                                                    ->label('Observações')
                                                    ->rows(2)
                                                    ->columnSpan(4),
                                            ])
                                            ->defaultItems(1)
                                            ->addActionLabel('Adicionar documento')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Contatos')
                            ->schema([
                                Section::make('Contatos')
                                    ->schema([
                                        Repeater::make('contacts')
                                            ->label('Contatos')
                                            ->relationship('contacts')
                                            ->columns(6)
                                            ->schema([
                                                Select::make('type')
                                                    ->label('Tipo')
                                                    ->required()
                                                    ->options([
                                                        'mobile' => 'Celular',
                                                        'phone' => 'Telefone',
                                                        'email' => 'E-mail',
                                                        'emergency_phone' => 'Telefone de emergência',
                                                        'other' => 'Outro',
                                                    ])
                                                    ->native(false)
                                                    ->columnSpan(2),

                                                TextInput::make('label')
                                                    ->label('Descrição')
                                                    ->maxLength(255)
                                                    ->columnSpan(1),

                                                TextInput::make('value')
                                                    ->label('Contato')
                                                    ->required()
                                                    ->dehydrateStateUsing(fn (?string $state): ?string => self::cleanContact($state))
                                                    ->maxLength(255)
                                                    ->columnSpan(2),

                                                Select::make('is_primary')
                                                    ->label('Principal')
                                                    ->options([
                                                        1 => 'Sim',
                                                        0 => 'Não',
                                                    ])
                                                    ->default(0)
                                                    ->native(false)
                                                    ->columnSpan(1),

                                                Textarea::make('notes')
                                                    ->label('Observações')
                                                    ->rows(2)
                                                    ->columnSpanFull(),
                                            ])
                                            ->defaultItems(2)
                                            ->addActionLabel('Adicionar contato')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Endereços')
                            ->schema([
                                Section::make('Endereços')
                                    ->schema([
                                        Repeater::make('addresses')
                                            ->label('Endereços')
                                            ->relationship('addresses')
                                            ->columns(6)
                                            ->schema([
                                                Select::make('type')
                                                    ->label('Tipo')
                                                    ->required()
                                                    ->default('residential')
                                                    ->options([
                                                        'residential' => 'Residencial',
                                                        'mailing' => 'Correspondência',
                                                        'other' => 'Outro',
                                                    ])
                                                    ->native(false)
                                                    ->columnSpan(2),

                                                TextInput::make('postal_code')
                                                    ->label('CEP')
                                                    ->mask('99999-999')
                                                    ->dehydrateStateUsing(fn (?string $state): ?string => self::digits($state))
                                                    ->columnSpan(1),

                                                TextInput::make('street')
                                                    ->label('Endereço')
                                                    ->maxLength(255)
                                                    ->columnSpan(2),

                                                TextInput::make('number')
                                                    ->label('Número')
                                                    ->maxLength(50)
                                                    ->columnSpan(1),

                                                TextInput::make('complement')
                                                    ->label('Complemento')
                                                    ->maxLength(255)
                                                    ->columnSpan(2),

                                                TextInput::make('district')
                                                    ->label('Bairro')
                                                    ->maxLength(255)
                                                    ->columnSpan(2),

                                                TextInput::make('city')
                                                    ->label('Cidade')
                                                    ->maxLength(255)
                                                    ->columnSpan(1),

                                                TextInput::make('state')
                                                    ->label('UF')
                                                    ->maxLength(2)
                                                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper(trim($state)) : null)
                                                    ->columnSpan(1),

                                                Select::make('is_primary')
                                                    ->label('Principal')
                                                    ->options([
                                                        1 => 'Sim',
                                                        0 => 'Não',
                                                    ])
                                                    ->default(0)
                                                    ->native(false)
                                                    ->columnSpan(1),

                                                Textarea::make('notes')
                                                    ->label('Observações')
                                                    ->rows(2)
                                                    ->columnSpan(5),
                                            ])
                                            ->defaultItems(1)
                                            ->addActionLabel('Adicionar endereço')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Jornada')
                            ->schema([
                                Section::make('Jornada e carga horária')
                                    ->description('Selecione uma jornada de trabalho previamente cadastrada. O sistema mantém os detalhes técnicos por trás.')
                                    ->schema([
                                        Repeater::make('workSchedules')
                                            ->label('Jornada atual')
                                            ->relationship('workSchedules')
                                            ->minItems(1)
                                            ->maxItems(1)
                                            ->defaultItems(1)
                                            ->addable(false)
                                            ->deletable(false)
                                            ->reorderable(false)
                                            ->columns(6)
                                            ->schema([
                                                Select::make('employee_work_schedule_template_id')
                                                    ->label('Jornada de trabalho')
                                                    ->helperText('Ex: Administrativo 44h — 08:00 às 12:00 - 13:00 às 17:48 - SAB DOM DSR')
                                                    ->options(fn (mixed $record = null): array => self::workScheduleTemplateOptions($record))
                                                    ->searchable()
                                                    ->preload()
                                                    ->required()
                                                    ->native(false)
                                                    ->live()
                                                    ->afterStateUpdated(function (?string $state, $set): void {
                                                        $template = self::workScheduleTemplate($state);

                                                        $set('name', $template?->name ?? 'Jornada principal');
                                                        $set('type', $template?->type ?? 'standard');
                                                        $set('weekly_workload_minutes', $template?->weekly_workload_minutes);
                                                        $set('daily_workload_minutes', $template?->daily_workload_minutes);
                                                        $set('tolerance_before_start_minutes', $template?->tolerance_before_start_minutes ?? 0);
                                                        $set('tolerance_after_end_minutes', 0);
                                                    })
                                                    ->columnSpan(4),

                                                DatePicker::make('valid_from')
                                                    ->label('Válida a partir de')
                                                    ->columnSpan(2),

                                                DatePicker::make('valid_until')
                                                    ->label('Válida até')
                                                    ->columnSpan(2),

                                                Select::make('is_active')
                                                    ->label('Ativa')
                                                    ->options([
                                                        1 => 'Sim',
                                                        0 => 'Não',
                                                    ])
                                                    ->default(1)
                                                    ->native(false)
                                                    ->columnSpan(2),

                                                Textarea::make('notes')
                                                    ->label('Observações da jornada do funcionário')
                                                    ->helperText('Use apenas para observações específicas deste funcionário.')
                                                    ->rows(2)
                                                    ->columnSpan(4),

                                                Hidden::make('name')
                                                    ->default('Jornada principal'),

                                                Hidden::make('type')
                                                    ->default('standard'),

                                                Hidden::make('weekly_workload_minutes'),

                                                Hidden::make('daily_workload_minutes'),

                                                Hidden::make('tolerance_before_start_minutes')
                                                    ->default(0),

                                                Hidden::make('tolerance_after_end_minutes')
                                                    ->default(0),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Observações')
                            ->schema([
                                Section::make('Observações')
                                    ->schema([
                                        Textarea::make('notes')
                                            ->label('Observações gerais')
                                            ->rows(4)
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function tenantId(?EmployeeRecord $record): ?string
    {
        return $record?->tenant_id
            ?: app(TenantContext::class)->currentTenantIdForUser(auth()->user());
    }

    private static function workScheduleTemplateOptions(mixed $record = null): array
    {
        $tenantId = self::tenantIdFromEmployeeOrSchedule($record);

        if (blank($tenantId)) {
            return [];
        }

        return EmployeeWorkScheduleTemplateRecord::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (EmployeeWorkScheduleTemplateRecord $template): array => [
                $template->id => $template->name.(filled($template->description) ? ' — '.$template->description : ''),
            ])
            ->all();
    }

    private static function tenantIdFromEmployeeOrSchedule(mixed $record = null): ?string
    {
        if ($record instanceof EmployeeRecord) {
            return self::tenantId($record);
        }

        if ($record instanceof EmployeeWorkScheduleRecord) {
            $record->loadMissing('employee');

            return $record->employee?->tenant_id
                ?: app(TenantContext::class)->currentTenantIdForUser(auth()->user());
        }

        return app(TenantContext::class)->currentTenantIdForUser(auth()->user());
    }

    private static function workScheduleTemplate(?string $templateId): ?EmployeeWorkScheduleTemplateRecord
    {
        if (blank($templateId)) {
            return null;
        }

        return EmployeeWorkScheduleTemplateRecord::query()->find($templateId);
    }

    private static function organizationOptions(?EmployeeRecord $record): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $query = OrganizationRecord::query()
            ->orderBy('unit_code')
            ->orderBy('display_name')
            ->orderBy('trade_name')
            ->orderBy('legal_name');

        app(TenantContext::class)->applyOrganizationScope($query, $user);
        app(TenantContext::class)->applyUserOrganizationScope($query, $user, 'id');

        return $query
            ->get()
            ->mapWithKeys(fn (OrganizationRecord $organization): array => [
                $organization->id => collect([
                    $organization->unit_code,
                    $organization->display_name ?: $organization->trade_name ?: $organization->legal_name,
                ])->filter()->implode(' - '),
            ])
            ->all();
    }

    private static function managerOptions(?EmployeeRecord $record): array
    {
        $tenantId = self::tenantId($record);

        if (blank($tenantId)) {
            return [];
        }

        return EmployeeRecord::query()
            ->where('tenant_id', $tenantId)
            ->when($record?->id, fn ($query) => $query->whereKeyNot($record->id))
            ->orderBy('full_name')
            ->pluck('full_name', 'id')
            ->all();
    }

    private static function userOptions(?EmployeeRecord $record): array
    {
        $tenantId = self::tenantId($record);

        if (blank($tenantId)) {
            return [];
        }

        return User::query()
            ->whereHas('tenants', fn ($query) => $query->where('tenants.id', $tenantId))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $user): array => [
                $user->id => $user->name.' <'.$user->email.'>',
            ])
            ->all();
    }

    private static function clean(?string $state): ?string
    {
        return filled($state) ? trim($state) : null;
    }

    private static function cleanDocument(?string $state): ?string
    {
        return filled($state)
            ? strtoupper(preg_replace('/[^0-9A-Z]/i', '', $state))
            : null;
    }

    private static function cleanContact(?string $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        $state = trim($state);

        if (str_contains($state, '@')) {
            return strtolower($state);
        }

        return self::digits($state);
    }

    private static function digits(?string $state): ?string
    {
        return filled($state)
            ? preg_replace('/\D+/', '', $state)
            : null;
    }
}
