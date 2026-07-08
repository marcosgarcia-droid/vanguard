<?php

namespace App\Modules\Identity\UI\Filament\Resources\PartnerRecords\Schemas;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\ClassificationOptionRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
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
                                        TextInput::make('official_document')
                                            ->label('Documento oficial')
                                            ->placeholder('CPF ou CNPJ')
                                            ->helperText('Digite o CPF ou CNPJ e clique em "Verificar CPF/CNPJ" antes de continuar.')
                                            ->required()
                                            ->live(debounce: 700)
                                            ->afterStateUpdated(function (?string $state, $set): void {
                                                $personType = PartnerRecord::personTypeFromOfficialDocument($state);

                                                if ($personType) {
                                                    $set('person_type', $personType);
                                                }
                                            })
                                            ->hintAction(
                                                Action::make('checkOfficialDocument')
                                                    ->label('Verificar CPF/CNPJ')
                                                    ->icon('heroicon-o-magnifying-glass')
                                                    ->color('primary')
                                                    ->action(function ($state, $set, ?PartnerRecord $record): void {
                                                        $personType = PartnerRecord::personTypeFromOfficialDocument($state);

                                                        if ($personType) {
                                                            $set('person_type', $personType);
                                                        }

                                                        self::notifyOfficialDocumentStatus($state, $record);
                                                    })
                                            )
                                            ->dehydrateStateUsing(fn (?string $state): ?string => PartnerRecord::normalizeOfficialDocument($state))
                                            ->rules([
                                                fn (?PartnerRecord $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                                    $number = PartnerRecord::normalizeOfficialDocument((string) $value);
                                                    $type = PartnerRecord::officialDocumentTypeFromNumber($number);

                                                    if (! $number || ! $type) {
                                                        $fail('Informe um CPF com 11 dígitos ou um CNPJ com 14 dígitos.');

                                                        return;
                                                    }

                                                    $tenantId = $record?->tenant_id
                                                        ?: app(TenantContext::class)->currentTenantIdForUser(auth()->user());

                                                    if (PartnerRecord::officialDocumentExistsForTenant($tenantId, $number, $record?->id)) {
                                                        $fail('Já existe um parceiro cadastrado com este CPF/CNPJ neste grupo empresarial.');
                                                    }
                                                },
                                            ])
                                            ->columnSpan(3),

                                        Select::make('person_type')
                                            ->label('Tipo de pessoa')
                                            ->options([
                                                'individual' => 'Física',
                                                'company' => 'Jurídica',
                                            ])
                                            ->required()
                                            ->default('individual')
                                            ->native(false)
                                            ->columnSpan(3),

                                        TextInput::make('name')
                                            ->label('Nome / Razão social')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(3),

                                        TextInput::make('trade_name')
                                            ->label('Nome fantasia / Apelido')
                                            ->maxLength(255)
                                            ->columnSpan(3),

                                        Select::make('profiles')
                                            ->label('Perfis do parceiro')
                                            ->multiple()
                                            ->options(fn (?PartnerRecord $record): array => self::classificationOptions('partner_profile', $record?->tenant_id))
                                            ->native(false)
                                            ->columnSpanFull(),

                                        Select::make('organization_id')
                                            ->label('Unidade relacionadaa')
                                            ->required()
                                            ->helperText('Selecione a unidade à qual este parceiro está relacionado.')
                                            ->options(fn (): array => self::organizationOptions())
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->native(false)
                                            ->columnSpan(3),

                                        Select::make('status')
                                            ->label('Status')
                                            ->options([
                                                'active' => 'Ativo',
                                                'inactive' => 'Inativo',
                                            ])
                                            ->required()
                                            ->default('active')
                                            ->native(false)
                                            ->columnSpan(3),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Outros documentos')
                            ->schema([
                                Section::make('Outros documentos')
                                    ->description('Inscrições, RG e demais documentos secundários do parceiro. CPF/CNPJ ficam no campo Documento oficial.')
                                    ->schema([
                                        Repeater::make('otherDocuments')
                                            ->label('Outros documentos')
                                            ->relationship('otherDocuments')
                                            ->defaultItems(0)
                                            ->addActionLabel('Adicionar documento')
                                            ->reorderable(false)
                                            ->columns(6)
                                            ->schema([
                                                Select::make('type')
                                                    ->label('Tipo')
                                                    ->options(fn (?PartnerRecord $record): array => self::classificationOptions('partner_document_type', $record?->tenant_id))
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
                                                    ->options(fn (?PartnerRecord $record): array => self::classificationOptions('partner_contact_type', $record?->tenant_id))
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
                                                    ->options(fn (?PartnerRecord $record): array => self::classificationOptions('partner_address_type', $record?->tenant_id))
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

    private static function classificationOptions(string $category, ?string $tenantId = null): array
    {
        $tenantId ??= app(TenantContext::class)->currentTenantIdForUser(auth()->user());

        if (! $tenantId) {
            return self::defaultClassificationOptions($category);
        }

        $options = ClassificationOptionRecord::query()
            ->where('tenant_id', $tenantId)
            ->where('category', $category)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'code')
            ->all();

        return $options !== [] ? $options : self::defaultClassificationOptions($category);
    }

    private static function defaultClassificationOptions(string $category): array
    {
        return match ($category) {
            'partner_profile' => [
                'customer' => 'Cliente',
                'supplier' => 'Fornecedor',
                'carrier' => 'Transportadora',
                'service_provider' => 'Prestador de serviço',
                'rural_producer' => 'Produtor rural',
                'other' => 'Outro',
            ],
            'partner_document_type' => [
                'state_registration' => 'Inscrição Estadual',
                'municipal_registration' => 'Inscrição Municipal',
                'rg' => 'RG',
                'other' => 'Outro',
            ],
            'partner_contact_type' => [
                'mobile' => 'Celular',
                'whatsapp' => 'WhatsApp',
                'phone' => 'Telefone',
                'email' => 'E-mail',
                'contact_person' => 'Pessoa de contato',
                'other' => 'Outro',
            ],
            'partner_address_type' => [
                'fiscal' => 'Fiscal',
                'operational' => 'Operacional',
                'billing' => 'Cobrança',
                'delivery' => 'Entrega',
                'other' => 'Outro',
            ],
            default => [],
        };
    }

    private static function organizationOptions(): array
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

    private static function notifyOfficialDocumentStatus(?string $value, ?PartnerRecord $record = null): void
    {
        $number = PartnerRecord::normalizeOfficialDocument($value);

        if (! $number) {
            Notification::make()
                ->title('Documento oficial não informado')
                ->body('Digite um CPF ou CNPJ antes de verificar.')
                ->warning()
                ->send();

            return;
        }

        $documentType = PartnerRecord::officialDocumentTypeFromNumber($number);

        if (! $documentType) {
            Notification::make()
                ->title('Documento oficial incompleto')
                ->body('Informe um CPF com 11 dígitos ou um CNPJ com 14 dígitos.')
                ->warning()
                ->send();

            return;
        }

        $tenantId = $record?->tenant_id
            ?: app(TenantContext::class)->currentTenantIdForUser(auth()->user());

        if (! $tenantId) {
            Notification::make()
                ->title('Grupo empresarial não definido')
                ->body('Selecione um grupo empresarial antes de verificar o documento.')
                ->warning()
                ->send();

            return;
        }

        $existingPartner = self::findPartnerByOfficialDocument($tenantId, $number, $record?->id);

        if ($existingPartner instanceof PartnerRecord) {
            Notification::make()
                ->title('Parceiro já cadastrado')
                ->body('Este CPF/CNPJ já está vinculado ao parceiro '.$existingPartner->display_name.'.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Documento oficial disponível')
            ->body(($documentType === 'cpf' ? 'CPF' : 'CNPJ').' ainda não cadastrado neste grupo empresarial.')
            ->success()
            ->send();
    }

    private static function findPartnerByOfficialDocument(?string $tenantId, ?string $value, ?string $ignorePartnerId = null): ?PartnerRecord
    {
        $number = PartnerRecord::normalizeOfficialDocument($value);

        if (! $tenantId || ! $number) {
            return null;
        }

        return PartnerRecord::query()
            ->where('tenant_id', $tenantId)
            ->when($ignorePartnerId, fn ($query) => $query->whereKeyNot($ignorePartnerId))
            ->whereHas('documents', fn ($query) => $query->where('normalized_number', $number))
            ->first();
    }
}
