<?php

namespace App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Schemas;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Rules\UniqueOrganizationCnpj;
use DateTimeImmutable;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class OrganizationRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                Hidden::make('id')
                    ->default(fn (): string => (string) Str::uuid())
                    ->required(),

                Tabs::make('Cadastro da organização')
                    ->id('organization-record-form-tabs')
                    ->persistTab()
                    ->tabs([
                        Tab::make('Unidade')
                            ->schema([
                                Section::make('Identificação da unidade')
                                    ->description('Dados usados pelo time no dia a dia para identificar a unidade.')
                                    ->columns(6)
                                    ->schema([
                                        TextInput::make('display_name')
                                            ->label('Nome da unidade')
                                            ->helperText('Nome usado pelo time no dia a dia. Ex: AGRONORTE TOCANTINÓPOLIS.')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(3),

                                        TextInput::make('unit_code')
                                            ->label('Código da unidade')
                                            ->helperText('Use um código curto para diferenciar filiais. Ex: TOC-01, TC-OFICINA-01.')
                                            ->maxLength(255)
                                            ->columnSpan(3),

                                        Select::make('tenant_id')
                                            ->label('Grupo empresarial')
                                            ->helperText('Define a qual grupo esta unidade/CNPJ pertence.')
                                            ->options(fn (): array => self::tenantOptions())
                                            ->default(fn (?OrganizationRecord $record): ?string => $record?->tenant_id
                                                ?: app(TenantContext::class)->currentTenantIdForUser(auth()->user()))
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->native(false)
                                            ->disabled(fn (): bool => ! self::canManageOrganizationTenant())
                                            ->dehydrated(true)
                                            ->columnSpan(2),
                                        Select::make('status')
                                            ->label('Status interno')
                                            ->helperText('Controle operacional do Vanguard. Não altera a situação cadastral na Receita.')
                                            ->options([
                                                'active' => 'Ativa',
                                                'inactive' => 'Inativa',
                                            ])
                                            ->required()
                                            ->default('active')
                                            ->columnSpan(2),

                                        TextInput::make('cnpj')
                                            ->label('CNPJ')
                                            ->placeholder('00.000.000/0000-00')
                                            ->mask('99.999.999/9999-99')
                                            ->helperText('Após salvar, o CNPJ só poderá ser alterado por uma ação específica de correção.')
                                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                                                ? preg_replace('/\D+/', '', $state)
                                                : null)
                                            ->rules([
                                                fn (?OrganizationRecord $record): UniqueOrganizationCnpj => new UniqueOrganizationCnpj($record?->id),
                                            ])
                                            ->maxLength(18)
                                            ->disabledOn('edit')
                                            ->suffixAction(
                                                Action::make('lookupCnpj')
                                                    ->label('Buscar CNPJ')
                                                    ->tooltip('Buscar CNPJ')
                                                    ->icon('heroicon-o-magnifying-glass')
                                                    ->button()
                                                    ->visible(fn (?OrganizationRecord $record): bool => $record === null)
                                                    ->action(fn ($get, $set): null => self::lookupCnpj($get, $set)),
                                                isInline: true,
                                            )
                                            ->columnSpan(4),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Dados CNPJ')
                            ->schema([
                                Section::make('Dados cadastrais da consulta CNPJ')
                                    ->description('Dados oficiais atualizados pela consulta CNPJ.')
                                    ->columns(6)
                                    ->schema([
                                        TextInput::make('tax_registration_status_name')
                                            ->label('Situação cadastral')
                                            ->placeholder('Não informado pela consulta')
                                            ->helperText('Atualizado pela consulta CNPJ.')
                                            ->maxLength(255)
                                            ->disabledOn('edit')
                                            ->columnSpan(3),

                                        TextInput::make('legal_name')
                                            ->label('Razão social')
                                            ->placeholder('Não informado pela consulta')
                                            ->helperText('Dado cadastral atualizado pela consulta CNPJ.')
                                            ->required()
                                            ->maxLength(255)
                                            ->disabledOn('edit')
                                            ->columnSpan(3),

                                        TextInput::make('trade_name')
                                            ->label('Nome fantasia')
                                            ->placeholder('Não informado pela consulta')
                                            ->helperText('Dado cadastral atualizado pela consulta CNPJ.')
                                            ->maxLength(255)
                                            ->disabledOn('edit')
                                            ->columnSpan(3),

                                        TextInput::make('establishment_type')
                                            ->label('Tipo de estabelecimento')
                                            ->placeholder('Não informado pela consulta')
                                            ->helperText('Dado cadastral atualizado pela consulta CNPJ.')
                                            ->maxLength(255)
                                            ->disabledOn('edit')
                                            ->columnSpan(3),

                                        TextInput::make('legal_nature_name')
                                            ->label('Natureza jurídica')
                                            ->placeholder('Não informado pela consulta')
                                            ->helperText('Dado cadastral atualizado pela consulta CNPJ.')
                                            ->maxLength(255)
                                            ->disabledOn('edit')
                                            ->columnSpan(3),

                                        TextInput::make('company_size_name')
                                            ->label('Porte')
                                            ->placeholder('Não informado pela consulta')
                                            ->helperText('Dado cadastral atualizado pela consulta CNPJ.')
                                            ->maxLength(255)
                                            ->disabledOn('edit')
                                            ->columnSpan(3),

                                        DatePicker::make('opened_at')
                                            ->label('Data de abertura')
                                            ->placeholder('Não informada pela consulta')
                                            ->helperText('Dado cadastral atualizado pela consulta CNPJ.')
                                            ->disabledOn('edit')
                                            ->columnSpan(2),

                                        DatePicker::make('tax_registration_status_date')
                                            ->label('Data da situação cadastral')
                                            ->placeholder('Não informada pela consulta')
                                            ->helperText('Dado cadastral atualizado pela consulta CNPJ.')
                                            ->disabledOn('edit')
                                            ->columnSpan(2),

                                        TextInput::make('share_capital')
                                            ->label('Capital social')
                                            ->placeholder('Não informado pela consulta')
                                            ->helperText('Dado cadastral atualizado pela consulta CNPJ.')
                                            ->prefix('R$')
                                            ->numeric()
                                            ->step('0.01')
                                            ->disabledOn('edit')
                                            ->columnSpan(1),

                                        Toggle::make('is_head_office')
                                            ->label('Matriz')
                                            ->helperText('Dado cadastral atualizado pela consulta CNPJ.')
                                            ->disabledOn('edit')
                                            ->columnSpan(1),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Operacional')
                            ->visible(fn (?OrganizationRecord $record): bool => $record !== null)
                            ->schema([
                                Section::make('Dados operacionais')
                                    ->description('Dados usados no dia a dia da unidade. Eles não substituem os dados fiscais recebidos pela consulta CNPJ.')
                                    ->visible(fn (?OrganizationRecord $record): bool => $record !== null)
                                    ->columns(6)
                                    ->schema([
                                        TextInput::make('operational_phone')
                                            ->label('Telefone operacional')
                                            ->placeholder('(00) 00000-0000')
                                            ->tel()
                                            ->maxLength(30)
                                            ->columnSpan(3),

                                        TextInput::make('operational_email')
                                            ->label('E-mail operacional')
                                            ->placeholder('operacional@empresa.com.br')
                                            ->email()
                                            ->maxLength(255)
                                            ->columnSpan(3),

                                        TextInput::make('operational_postal_code')
                                            ->label('CEP operacional')
                                            ->placeholder('00000-000')
                                            ->mask('99999-999')
                                            ->helperText('Será salvo sem máscara no banco de dados.')
                                            ->maxLength(9)
                                            ->columnSpan(2),

                                        TextInput::make('operational_street')
                                            ->label('Endereço operacional')
                                            ->maxLength(255)
                                            ->columnSpan(3),

                                        TextInput::make('operational_number')
                                            ->label('Número')
                                            ->maxLength(50)
                                            ->columnSpan(1),

                                        TextInput::make('operational_complement')
                                            ->label('Complemento')
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        TextInput::make('operational_district')
                                            ->label('Bairro')
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        TextInput::make('operational_city')
                                            ->label('Cidade')
                                            ->maxLength(255)
                                            ->columnSpan(1),

                                        TextInput::make('operational_state')
                                            ->label('UF')
                                            ->placeholder('MG')
                                            ->maxLength(2)
                                            ->columnSpan(1),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Observações')
                            ->schema([
                                Section::make('Observações')
                                    ->description('Anotações internas da organização.')
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

    private static function tenantOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        if (self::canManageOrganizationTenant()) {
            return TenantRecord::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        }

        return app(TenantContext::class)
            ->availableTenantsForUser($user)
            ->mapWithKeys(fn (TenantRecord $tenant): array => [
                $tenant->id => $tenant->name,
            ])
            ->all();
    }

    private static function canManageOrganizationTenant(): bool
    {
        return auth()->user()?->hasRole(config('filament-shield.super_admin.name', 'super_admin')) ?? false;
    }

    private static function lookupCnpj(mixed $get, mixed $set): null
    {
        $cnpj = preg_replace('/\D+/', '', (string) $get('cnpj'));

        if (strlen($cnpj) !== 14) {
            Notification::make()
                ->title('Informe um CNPJ válido')
                ->body('Digite os 14 números do CNPJ antes de buscar.')
                ->danger()
                ->send();

            return null;
        }

        if (DB::table('organizations')->where('cnpj', $cnpj)->exists()) {
            Notification::make()
                ->title('CNPJ já cadastrado')
                ->body('Este CNPJ já existe no cadastro de organizações. Abra o registro existente para visualizar ou sincronizar os dados.')
                ->danger()
                ->send();

            return null;
        }

        try {
            $result = app(CnpjLookupProvider::class)->lookup(new Cnpj($cnpj));

            $payload = is_array($result->normalizedPayload ?? null)
                ? $result->normalizedPayload
                : [];

            $legalName = self::firstFilled([
                $result->legalName ?? null,
                data_get($payload, 'legal_name'),
                data_get($payload, 'company.legal_name'),
                data_get($payload, 'name'),
                data_get($payload, 'razao_social'),
            ]);

            $tradeName = self::firstFilled([
                $result->tradeName ?? null,
                data_get($payload, 'trade_name'),
                data_get($payload, 'company.trade_name'),
                data_get($payload, 'fantasy_name'),
                data_get($payload, 'nome_fantasia'),
            ]);

            $registrationStatus = self::firstFilled([
                $result->registrationStatusName ?? null,
                data_get($payload, 'registration_status_name'),
                data_get($payload, 'registration_status.name'),
                data_get($payload, 'tax_registration_status.name'),
                data_get($payload, 'descricao_situacao_cadastral'),
                data_get($payload, 'situacao'),
            ]);

            $establishmentType = self::firstFilled([
                data_get($payload, 'establishment_type'),
                data_get($payload, 'establishment.type'),
                data_get($payload, 'descricao_identificador_matriz_filial'),
                data_get($payload, 'tipo'),
            ]);

            $legalNature = self::firstFilled([
                $result->legalNatureName ?? null,
                data_get($payload, 'legal_nature_name'),
                data_get($payload, 'legal_nature.name'),
                data_get($payload, 'legal_nature'),
                data_get($payload, 'natureza_juridica'),
            ]);

            $companySize = self::firstFilled([
                $result->companySizeName ?? null,
                data_get($payload, 'company_size_name'),
                data_get($payload, 'company_size.name'),
                data_get($payload, 'company_size'),
                data_get($payload, 'porte'),
            ]);

            $openedAt = self::formatDate(self::firstFilled([
                $result->openedAt ?? null,
                data_get($payload, 'opened_at'),
                data_get($payload, 'data_inicio_atividade'),
                data_get($payload, 'abertura'),
            ]));

            $statusDate = self::formatDate(self::firstFilled([
                data_get($payload, 'registration_status_date'),
                data_get($payload, 'tax_registration_status.date'),
                data_get($payload, 'registration_status.date'),
                data_get($payload, 'data_situacao_cadastral'),
                data_get($payload, 'data_situacao'),
            ]));

            $shareCapital = self::firstFilled([
                $result->shareCapital ?? null,
                data_get($payload, 'share_capital'),
                data_get($payload, 'capital_social'),
            ]);

            $set('cnpj', self::formatCnpj($cnpj));
            $set('legal_name', $legalName);
            $set('trade_name', $tradeName);
            $set('tax_registration_status_name', $registrationStatus);
            $set('establishment_type', $establishmentType);
            $set('legal_nature_name', $legalNature);
            $set('opened_at', $openedAt);
            $set('tax_registration_status_date', $statusDate);
            $set('share_capital', $shareCapital);
            $set('company_size_name', $companySize);

            if (blank($get('display_name')) && filled($tradeName)) {
                $set('display_name', $tradeName);
            }

            if ($establishmentType !== null) {
                $set('is_head_office', str_contains(mb_strtoupper((string) $establishmentType), 'MATRIZ'));
            }

            $missingFields = self::missingFields([
                'Nome fantasia' => $tradeName,
                'Situação cadastral' => $registrationStatus,
                'Tipo de estabelecimento' => $establishmentType,
                'Natureza jurídica' => $legalNature,
                'Data da situação cadastral' => $statusDate,
                'Porte' => $companySize,
            ]);

            Notification::make()
                ->title('Consulta CNPJ concluída')
                ->body($missingFields === []
                    ? 'Dados cadastrais encontrados. Complete a identidade operacional da unidade.'
                    : 'A consulta retornou dados parciais. Campos não informados pela API: '.implode(', ', $missingFields).'.')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Consulta CNPJ indisponível')
                ->body('Não conseguimos consultar os serviços de CNPJ agora. Isso pode acontecer por instabilidade ou limite temporário das APIs gratuitas. Tente novamente mais tarde.')
                ->warning()
                ->send();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return list<string>
     */
    private static function missingFields(array $fields): array
    {
        $missing = [];

        foreach ($fields as $label => $value) {
            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === null || $value === '' || $value === []) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private static function firstFilled(array $values): mixed
    {
        foreach ($values as $value) {
            if (is_string($value)) {
                $value = trim($value);

                if ($value !== '') {
                    return $value;
                }

                continue;
            }

            if ($value !== null && $value !== []) {
                return $value;
            }
        }

        return null;
    }

    private static function formatDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
            return substr($value, 0, 10);
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value) === 1) {
            $date = DateTimeImmutable::createFromFormat('d/m/Y', $value);

            return $date === false ? null : $date->format('Y-m-d');
        }

        return null;
    }

    private static function formatCnpj(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);

        if (strlen($digits) !== 14) {
            return $value;
        }

        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($digits, 0, 2),
            substr($digits, 2, 3),
            substr($digits, 5, 3),
            substr($digits, 8, 4),
            substr($digits, 12, 2),
        );
    }
}
