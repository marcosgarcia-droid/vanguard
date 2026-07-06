<?php

namespace App\Modules\Identity\UI\Filament\Resources\PartnerRecords\Schemas;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PartnerRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                Hidden::make('id')
                    ->default(fn (): string => (string) Str::uuid())
                    ->required(),

                Tabs::make('Cadastro do parceiro')
                    ->id('partner-record-form-tabs')
                    ->persistTab()
                    ->tabs([
                        Tab::make('Parceiro')
                            ->schema([
                                Section::make('Dados principais')
                                    ->description('Identificação principal do parceiro comercial ou operacional.')
                                    ->columns(6)
                                    ->schema([

                                        Select::make('person_type')
                                            ->label('Tipo de pessoa')
                                            ->options([
                                                'company' => 'Jurídica',
                                                'individual' => 'Física',
                                            ])
                                            ->required()
                                            ->default('company')
                                            ->native(false)
                                            ->columnSpan(2),

                                        Select::make('status')
                                            ->label('Status')
                                            ->options([
                                                'active' => 'Ativo',
                                                'inactive' => 'Inativo',
                                            ])
                                            ->required()
                                            ->default('active')
                                            ->native(false)
                                            ->columnSpan(2),

                                        TextInput::make('name')
                                            ->label('Nome / Razão social')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(3),

                                        TextInput::make('trade_name')
                                            ->label('Nome fantasia / Apelido')
                                            ->maxLength(255)
                                            ->columnSpan(3),

                                        Select::make('organization_id')
                                            ->label('Unidade relacionada')
                                            ->helperText('Opcional. Use quando o parceiro estiver ligado a uma unidade específica.')
                                            ->options(fn (): array => self::organizationOptions())
                                            ->searchable()
                                            ->preload()
                                            ->native(false)
                                            ->columnSpan(3),

                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Documentos')
                            ->schema([
                                Section::make('Documentos')
                                    ->description('CNPJ, CPF, inscrições e demais documentos do parceiro.')
                                    ->schema([
                                        Repeater::make('documents')
                                            ->label('Documentos')
                                            ->relationship('documents')
                                            ->defaultItems(0)
                                            ->addActionLabel('Adicionar documento')
                                            ->reorderable(false)
                                            ->columns(6)
                                            ->schema([
                                                Select::make('type')
                                                    ->label('Tipo')
                                                    ->options([
                                                        'cnpj' => 'CNPJ',
                                                        'cpf' => 'CPF',
                                                        'state_registration' => 'Inscrição Estadual',
                                                        'municipal_registration' => 'Inscrição Municipal',
                                                        'rg' => 'RG',
                                                        'other' => 'Outro',
                                                    ])
                                                    ->required()
                                                    ->native(false)
                                                    ->columnSpan(2),

                                                TextInput::make('number')
                                                    ->label('Número')
                                                    ->required()
                                                    ->dehydrateStateUsing(fn (?string $state): ?string => self::cleanDocument($state))
                                                    ->maxLength(255)
                                                    ->columnSpan(2),

                                                TextInput::make('state')
                                                    ->label('UF')
                                                    ->maxLength(2)
                                                    ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper(trim($state)) : null)
                                                    ->columnSpan(1),

                                                Toggle::make('is_primary')
                                                    ->label('Principal')
                                                    ->default(false)
                                                    ->dehydrateStateUsing(fn (mixed $state): bool => (bool) $state)
                                                    ->columnSpan(1),

                                                TextInput::make('issuing_authority')
                                                    ->label('Órgão emissor')
                                                    ->maxLength(255)
                                                    ->columnSpan(2),

                                                DatePicker::make('issued_at')
                                                    ->label('Emissão')
                                                    ->columnSpan(2),

                                                DatePicker::make('expires_at')
                                                    ->label('Validade')
                                                    ->columnSpan(2),

                                                Textarea::make('notes')
                                                    ->label('Observações')
                                                    ->rows(2)
                                                    ->columnSpanFull(),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Contatos')
                            ->schema([
                                Section::make('Contatos')
                                    ->description('Telefones, WhatsApp, e-mails e contatos comerciais.')
                                    ->schema([
                                        Repeater::make('contacts')
                                            ->label('Contatos')
                                            ->relationship('contacts')
                                            ->defaultItems(0)
                                            ->addActionLabel('Adicionar contato')
                                            ->reorderable(false)
                                            ->columns(6)
                                            ->schema([
                                                Select::make('type')
                                                    ->label('Tipo')
                                                    ->options([
                                                        'mobile' => 'Celular',
                                                        'whatsapp' => 'WhatsApp',
                                                        'phone' => 'Telefone',
                                                        'email' => 'E-mail',
                                                        'contact_person' => 'Pessoa de contato',
                                                        'other' => 'Outro',
                                                    ])
                                                    ->required()
                                                    ->native(false)
                                                    ->columnSpan(2),

                                                TextInput::make('label')
                                                    ->label('Descrição')
                                                    ->placeholder('Ex: Comercial, Financeiro')
                                                    ->maxLength(255)
                                                    ->columnSpan(2),

                                                TextInput::make('value')
                                                    ->label('Telefone, e-mail ou nome do contato')
                                                    ->helperText('Obrigatório apenas quando um contato for adicionado.')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpan(2),

                                                Toggle::make('is_primary')
                                                    ->label('Principal')
                                                    ->default(false)
                                                    ->dehydrateStateUsing(fn (mixed $state): bool => (bool) $state)
                                                    ->columnSpan(1),

                                                Textarea::make('notes')
                                                    ->label('Observações')
                                                    ->rows(2)
                                                    ->columnSpan(5),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Endereços')
                            ->schema([
                                Section::make('Endereços')
                                    ->description('Endereços fiscais, operacionais, de cobrança e entrega.')
                                    ->schema([
                                        Repeater::make('addresses')
                                            ->label('Endereços')
                                            ->relationship('addresses')
                                            ->defaultItems(0)
                                            ->addActionLabel('Adicionar endereço')
                                            ->reorderable(false)
                                            ->columns(6)
                                            ->schema([
                                                Select::make('type')
                                                    ->label('Tipo')
                                                    ->options([
                                                        'fiscal' => 'Fiscal',
                                                        'operational' => 'Operacional',
                                                        'billing' => 'Cobrança',
                                                        'delivery' => 'Entrega',
                                                        'other' => 'Outro',
                                                    ])
                                                    ->required()
                                                    ->default('operational')
                                                    ->native(false)
                                                    ->columnSpan(2),

                                                TextInput::make('postal_code')
                                                    ->label('CEP')
                                                    ->mask('99999-999')
                                                    ->helperText('Será salvo sem máscara no banco de dados.')
                                                    ->dehydrateStateUsing(fn (?string $state): ?string => self::digits($state))
                                                    ->columnSpan(2),

                                                Toggle::make('is_primary')
                                                    ->label('Principal')
                                                    ->default(false)
                                                    ->dehydrateStateUsing(fn (mixed $state): bool => (bool) $state)
                                                    ->columnSpan(2),

                                                TextInput::make('street')
                                                    ->label('Endereço')
                                                    ->maxLength(255)
                                                    ->columnSpan(3),

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
                                                    ->columnSpan(2),

                                                TextInput::make('state')
                                                    ->label('UF')
                                                    ->maxLength(2)
                                                    ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper(trim($state)) : null)
                                                    ->columnSpan(1),

                                                Textarea::make('notes')
                                                    ->label('Observações')
                                                    ->rows(2)
                                                    ->columnSpanFull(),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Classificações')
                            ->schema([
                                Section::make('Classificações')
                                    ->description('Informe uma ou mais classificações para o parceiro.')
                                    ->columns(6)
                                    ->schema([
                                        Select::make('profiles')
                                            ->label('Perfis do parceiro')
                                            ->multiple()
                                            ->options([
                                                'customer' => 'Cliente',
                                                'supplier' => 'Fornecedor',
                                                'carrier' => 'Transportadora',
                                                'service_provider' => 'Prestador de serviço',
                                                'rural_producer' => 'Produtor rural',
                                                'other' => 'Outro',
                                            ])
                                            ->native(false)
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Observações')
                            ->schema([
                                Section::make('Observações')
                                    ->description('Anotações internas do parceiro.')
                                    ->schema([
                                        Textarea::make('notes')
                                            ->label('Observações')
                                            ->rows(6)
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function organizationOptions(): array
    {
        $tenantId = app(TenantContext::class)->currentTenantIdForUser(auth()->user());

        if (! $tenantId) {
            return [];
        }

        return OrganizationRecord::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('display_name')
            ->pluck('display_name', 'id')
            ->all();
    }

    private static function cleanDocument(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', trim($value)));

        return $clean !== '' ? $clean : null;
    }

    private static function digits(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' ? $digits : null;
    }
}
