<?php

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Schemas;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
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
                                    ->description('Base para controle de jornada e futuro controle de acesso físico.')
                                    ->schema([
                                        Repeater::make('workSchedules')
                                            ->label('Jornadas')
                                            ->relationship('workSchedules')
                                            ->columns(6)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Nome da jornada')
                                                    ->default('Jornada principal')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpan(2),

                                                Select::make('type')
                                                    ->label('Tipo')
                                                    ->required()
                                                    ->default('fixed')
                                                    ->options([
                                                        'fixed' => 'Fixa',
                                                        'shift' => 'Turno',
                                                        'flexible' => 'Flexível',
                                                    ])
                                                    ->native(false)
                                                    ->columnSpan(1),

                                                TextInput::make('weekly_workload_minutes')
                                                    ->label('Carga semanal em minutos')
                                                    ->helperText('44h = 2640 minutos.')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->columnSpan(1),

                                                TextInput::make('daily_workload_minutes')
                                                    ->label('Carga diária em minutos')
                                                    ->helperText('8h48 = 528 minutos.')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->columnSpan(1),

                                                Select::make('is_active')
                                                    ->label('Ativa')
                                                    ->options([
                                                        1 => 'Sim',
                                                        0 => 'Não',
                                                    ])
                                                    ->default(1)
                                                    ->native(false)
                                                    ->columnSpan(1),

                                                TextInput::make('tolerance_before_start_minutes')
                                                    ->label('Tolerância antes do início')
                                                    ->helperText('Máximo recomendado: 30 minutos.')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(30)
                                                    ->default(0)
                                                    ->columnSpan(2),

                                                TextInput::make('tolerance_after_end_minutes')
                                                    ->label('Tolerância após o fim')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->default(0)
                                                    ->columnSpan(2),

                                                DatePicker::make('valid_from')
                                                    ->label('Válida a partir de')
                                                    ->columnSpan(1),

                                                DatePicker::make('valid_until')
                                                    ->label('Válida até')
                                                    ->columnSpan(1),

                                                Repeater::make('days')
                                                    ->label('Dias da jornada')
                                                    ->relationship('days')
                                                    ->columns(7)
                                                    ->schema([
                                                        Select::make('weekday')
                                                            ->label('Dia')
                                                            ->required()
                                                            ->options([
                                                                1 => 'Segunda',
                                                                2 => 'Terça',
                                                                3 => 'Quarta',
                                                                4 => 'Quinta',
                                                                5 => 'Sexta',
                                                                6 => 'Sábado',
                                                                7 => 'Domingo',
                                                            ])
                                                            ->native(false)
                                                            ->columnSpan(1),

                                                        Select::make('is_working_day')
                                                            ->label('Trabalha')
                                                            ->options([
                                                                1 => 'Sim',
                                                                0 => 'Não',
                                                            ])
                                                            ->default(1)
                                                            ->native(false)
                                                            ->columnSpan(1),

                                                        TimePicker::make('work_starts_at')
                                                            ->label('Entrada')
                                                            ->seconds(false)
                                                            ->columnSpan(1),

                                                        TimePicker::make('work_ends_at')
                                                            ->label('Saída')
                                                            ->seconds(false)
                                                            ->columnSpan(1),

                                                        Select::make('ends_next_day')
                                                            ->label('Vira dia')
                                                            ->options([
                                                                1 => 'Sim',
                                                                0 => 'Não',
                                                            ])
                                                            ->default(0)
                                                            ->native(false)
                                                            ->columnSpan(1),

                                                        TimePicker::make('break_starts_at')
                                                            ->label('Início intervalo')
                                                            ->seconds(false)
                                                            ->columnSpan(1),

                                                        TimePicker::make('break_ends_at')
                                                            ->label('Fim intervalo')
                                                            ->seconds(false)
                                                            ->columnSpan(1),
                                                    ])
                                                    ->addActionLabel('Adicionar dia')
                                                    ->columnSpanFull(),

                                                Textarea::make('notes')
                                                    ->label('Observações da jornada')
                                                    ->rows(2)
                                                    ->columnSpanFull(),
                                            ])
                                            ->defaultItems(1)
                                            ->addActionLabel('Adicionar jornada')
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

    private static function organizationOptions(?EmployeeRecord $record): array
    {
        $tenantId = self::tenantId($record);

        if (blank($tenantId)) {
            return [];
        }

        return OrganizationRecord::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('display_name')
            ->get()
            ->mapWithKeys(fn (OrganizationRecord $organization): array => [
                $organization->id => $organization->operational_name,
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
