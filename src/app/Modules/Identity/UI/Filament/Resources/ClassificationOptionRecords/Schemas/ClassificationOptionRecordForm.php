<?php

namespace App\Modules\Identity\UI\Filament\Resources\ClassificationOptionRecords\Schemas;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\ClassificationOptionRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ClassificationOptionRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                Hidden::make('id')
                    ->default(fn (): string => (string) Str::uuid())
                    ->required(),

                Hidden::make('is_system')
                    ->default(false),

                Tabs::make('Cadastro da classificação')
                    ->id('classification-option-form-tabs')
                    ->persistTab()
                    ->tabs([
                        Tab::make('Classificação')
                            ->schema([
                                Section::make('Dados principais')
                                    ->description('Classificações usadas em cadastros como Parceiros, Documentos, Contatos e Endereços.')
                                    ->columns(6)
                                    ->schema([
                                        Select::make('tenant_id')
                                            ->label('Grupo empresarial')
                                            ->helperText('Obrigatório quando estiver na Visão Global.')
                                            ->options(fn (): array => self::tenantOptions())
                                            ->default(
                                                fn (): ?string => app(TenantContext::class)
                                                    ->currentTenantIdForUser(auth()->user())
                                            )
                                            ->required(
                                                fn (): bool => self::requiresTenantSelection()
                                            )
                                            ->visible(
                                                fn (?ClassificationOptionRecord $record): bool => $record === null
                                                    && self::requiresTenantSelection()
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->native(false)
                                            ->columnSpan(3),

                                        Select::make('category')
                                            ->label('Categoria')
                                            ->options(self::categoryOptions())
                                            ->required()
                                            ->native(false)
                                            ->disabled(fn (?ClassificationOptionRecord $record): bool => $record?->is_system ?? false)
                                            ->columnSpan(3),

                                        Select::make('status')
                                            ->label('Status')
                                            ->options([
                                                'active' => 'Ativa',
                                                'inactive' => 'Inativa',
                                            ])
                                            ->required()
                                            ->default('active')
                                            ->native(false)
                                            ->columnSpan(2),

                                        TextInput::make('sort_order')
                                            ->label('Ordem')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->columnSpan(1),

                                        TextInput::make('name')
                                            ->label('Nome')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(3),

                                        TextInput::make('code')
                                            ->label('Código interno')
                                            ->helperText('Gerado automaticamente a partir do nome. Use somente se precisar ajustar uma integração.')
                                            ->maxLength(120)
                                            ->disabled(fn (?ClassificationOptionRecord $record): bool => $record?->is_system ?? false)
                                            ->dehydrateStateUsing(fn (?string $state, $get): ?string => filled($state)
                                                ? $state
                                                : $get('name'))
                                            ->columnSpan(3),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Descrição')
                            ->schema([
                                Section::make('Descrição')
                                    ->schema([
                                        Textarea::make('description')
                                            ->label('Descrição')
                                            ->rows(6)
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function requiresTenantSelection(): bool
    {
        $user = auth()->user();

        return $user?->hasRole(
            config('filament-shield.super_admin.name', 'super_admin')
        )
            && app(TenantContext::class)
                ->currentTenantIdForUser($user) === null;
    }

    /**
     * @return array<string, string>
     */
    private static function tenantOptions(): array
    {
        return TenantRecord::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function categoryOptions(): array
    {
        return [
            'partner_profile' => 'Perfil de parceiro',
            'partner_document_type' => 'Tipo de documento de parceiro',
            'partner_contact_type' => 'Tipo de contato de parceiro',
            'partner_address_type' => 'Tipo de endereço de parceiro',
            'visitor_document_type' => 'Tipo de documento de visitante',
            'visitor_contact_type' => 'Tipo de contato de visitante',
        ];
    }
}
