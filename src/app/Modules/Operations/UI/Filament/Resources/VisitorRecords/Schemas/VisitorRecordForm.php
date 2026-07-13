<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Schemas;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\ClassificationOptionRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class VisitorRecordForm
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

                Hidden::make('photo_disk')
                    ->default('local')
                    ->required(),

                Tabs::make('Cadastro do visitante')
                    ->id('visitor-record-form-tabs')
                    ->persistTab()
                    ->tabs([
                        Tab::make('Visitante')
                            ->schema([
                                Section::make('Dados principais')
                                    ->description('Identificação e situação cadastral do visitante.')
                                    ->columns(6)
                                    ->schema([
                                        FileUpload::make('photo_path')
                                            ->label('Foto')
                                            ->helperText('Imagem JPG, PNG ou WebP, com no máximo 5 MB.')
                                            ->image()
                                            ->acceptedFileTypes([
                                                'image/jpeg',
                                                'image/png',
                                                'image/webp',
                                            ])
                                            ->maxSize(5120)
                                            ->disk('local')
                                            ->visibility('private')
                                            ->directory('visitors/photos')
                                            ->imagePreviewHeight('180')
                                            ->downloadable(false)
                                            ->openable(false)
                                            ->columnSpan(2),

                                        Select::make('status')
                                            ->label('Status')
                                            ->options(VisitorStatus::options())
                                            ->default(VisitorStatus::Active->value)
                                            ->required()
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

                                        DatePicker::make('birth_date')
                                            ->label('Data de nascimento')
                                            ->maxDate(now())
                                            ->columnSpan(2),

                                        Select::make('organization_id')
                                            ->label('Unidade')
                                            ->helperText('Unidade responsável pelo cadastro e pelas visitas.')
                                            ->options(fn (): array => self::organizationOptions())
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(
                                                fn ($set) => $set('partner_id', null)
                                            )
                                            ->native(false)
                                            ->columnSpan(2),

                                        Select::make('partner_id')
                                            ->label('Parceiro / empresa representada')
                                            ->helperText('Opcional. São exibidos apenas parceiros da unidade selecionada.')
                                            ->options(
                                                fn ($get, ?VisitorRecord $record): array => self::partnerOptions(
                                                    $get('organization_id'),
                                                    $record
                                                )
                                            )
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
                                    ->description('Os documentos são armazenados sem máscara. A formatação é aplicada apenas na interface.')
                                    ->schema([
                                        Repeater::make('documents')
                                            ->label('Documentos')
                                            ->relationship('documents')
                                            ->defaultItems(1)
                                            ->addActionLabel('Adicionar documento')
                                            ->reorderable(false)
                                            ->columns(6)
                                            ->schema([
                                                Select::make('type')
                                                    ->label('Tipo')
                                                    ->options(
                                                        fn (Get $get): array => self::classificationOptions(
                                                            'visitor_document_type',
                                                            self::tenantIdFromRepeater($get)
                                                        )
                                                    )
                                                    ->default('cpf')
                                                    ->required()
                                                    ->native(false)
                                                    ->columnSpan(2),

                                                TextInput::make('number')
                                                    ->label('Número')
                                                    ->helperText('CPF, RG, CNH, passaporte ou outro documento.')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpan(2),

                                                TextInput::make('state')
                                                    ->label('UF')
                                                    ->maxLength(2)
                                                    ->columnSpan(1),

                                                Toggle::make('is_primary')
                                                    ->label('Principal')
                                                    ->default(true)
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
                                    ->description('Telefones e demais contatos são normalizados antes de serem armazenados.')
                                    ->schema([
                                        Repeater::make('contacts')
                                            ->label('Contatos')
                                            ->relationship('contacts')
                                            ->defaultItems(1)
                                            ->addActionLabel('Adicionar contato')
                                            ->reorderable(false)
                                            ->columns(6)
                                            ->schema([
                                                Select::make('type')
                                                    ->label('Tipo')
                                                    ->options(
                                                        fn (Get $get): array => self::classificationOptions(
                                                            'visitor_contact_type',
                                                            self::tenantIdFromRepeater($get)
                                                        )
                                                    )
                                                    ->default('mobile')
                                                    ->required()
                                                    ->native(false)
                                                    ->columnSpan(2),

                                                TextInput::make('label')
                                                    ->label('Descrição')
                                                    ->placeholder('Ex: Celular pessoal')
                                                    ->maxLength(255)
                                                    ->columnSpan(2),

                                                TextInput::make('value')
                                                    ->label('Contato')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpan(2),

                                                Toggle::make('is_primary')
                                                    ->label('Principal')
                                                    ->default(true)
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

                        Tab::make('Observações')
                            ->schema([
                                Section::make('Observações')
                                    ->description('Anotações internas sobre o visitante.')
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

    private static function tenantId(?VisitorRecord $record): ?string
    {
        return $record?->tenant_id
            ?: app(TenantContext::class)
                ->currentTenantIdForUser(auth()->user());
    }

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
            ->mapWithKeys(fn (OrganizationRecord $organization): array => [
                $organization->id => collect([
                    $organization->unit_code,
                    $organization->operational_name,
                ])->filter()->implode(' - '),
            ])
            ->all();
    }

    private static function partnerOptions(
        ?string $organizationId,
        ?VisitorRecord $record
    ): array {
        $user = auth()->user();

        if (! $user || blank($organizationId)) {
            return [];
        }

        $organization = OrganizationRecord::query()
            ->whereKey($organizationId)
            ->where('status', 'active')
            ->first();

        if (! $organization instanceof OrganizationRecord) {
            return [];
        }

        $tenantId = $record?->tenant_id
            ?: $organization->tenant_id;

        $query = PartnerRecord::query()
            ->where('tenant_id', $tenantId)
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->orderBy('trade_name')
            ->orderBy('name');

        app(TenantContext::class)->applyUserOrganizationScope(
            $query,
            $user
        );

        return $query
            ->get()
            ->mapWithKeys(fn (PartnerRecord $partner): array => [
                $partner->id => $partner->display_name,
            ])
            ->all();
    }

    private static function tenantIdFromRepeater(Get $get): ?string
    {
        $tenantId = $get('../../tenant_id');

        if (filled($tenantId)) {
            return (string) $tenantId;
        }

        $organizationId = $get('../../organization_id');

        if (filled($organizationId)) {
            $organizationTenantId = OrganizationRecord::query()
                ->whereKey($organizationId)
                ->value('tenant_id');

            if (filled($organizationTenantId)) {
                return (string) $organizationTenantId;
            }
        }

        return app(TenantContext::class)
            ->currentTenantIdForUser(auth()->user());
    }

    private static function classificationOptions(
        string $category,
        ?string $tenantId
    ): array {
        if (filled($tenantId)) {
            $options = ClassificationOptionRecord::query()
                ->where('tenant_id', $tenantId)
                ->where('category', $category)
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('name', 'code')
                ->all();

            if ($options !== []) {
                return $options;
            }
        }

        return match ($category) {
            'visitor_document_type' => [
                'cpf' => 'CPF',
                'rg' => 'RG',
                'cnh' => 'CNH',
                'passport' => 'Passaporte',
                'foreign_document' => 'Documento estrangeiro',
                'other' => 'Outro',
            ],
            'visitor_contact_type' => [
                'mobile' => 'Celular',
                'whatsapp' => 'WhatsApp',
                'phone' => 'Telefone',
                'email' => 'E-mail',
                'emergency_phone' => 'Telefone de emergência',
                'other' => 'Outro',
            ],
            default => [],
        };
    }
}
