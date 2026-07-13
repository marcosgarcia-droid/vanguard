<?php

namespace App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Tables;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Actions\CorrectOrganizationCnpjAction;
use App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Actions\SyncOrganizationCnpjAction;
use App\Support\ActivityLog\VanguardActivityLogTimelineAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OrganizationRecordsTable
{
    private const SOURCE_OPERATIONAL_MANUAL = 'operational_manual';

    private const OPERATIONAL_FORM_FIELDS = [
        'operational_phone',
        'operational_email',
        'operational_postal_code',
        'operational_street',
        'operational_number',
        'operational_complement',
        'operational_district',
        'operational_city',
        'operational_state',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $query->with(['tenant', 'addresses', 'contacts']);

                app(TenantContext::class)->applyOrganizationScope($query, auth()->user());
                app(TenantContext::class)->applyUserOrganizationScope($query, auth()->user(), 'id');

                return $query;
            })
            ->defaultSort('display_name')
            ->columns([
                TextColumn::make('tenant.name')
                    ->label('Grupo')
                    ->placeholder('-')
                    ->toggleable()
                    ->sortable(),

                TextColumn::make('display_name')
                    ->label('Unidade')
                    ->formatStateUsing(fn (?string $state, OrganizationRecord $record): string => $record->operational_name)
                    ->searchable(['display_name', 'unit_code', 'legal_name', 'trade_name', 'cnpj', 'cnpj_formatted'])
                    ->sortable(),

                TextColumn::make('unit_code')
                    ->label('Código')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('city_state')
                    ->label('Cidade/UF')
                    ->placeholder('-'),

                TextColumn::make('cnpj')
                    ->label('CNPJ')
                    ->formatStateUsing(fn (?string $state): string => self::formatCnpj($state))
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('tax_registration_status_name')
                    ->label('Situação')
                    ->badge()
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('primary_contact_display')
                    ->label('Contato')
                    ->placeholder('-'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                VanguardActivityLogTimelineAction::make(),

                SyncOrganizationCnpjAction::make(),

                CorrectOrganizationCnpjAction::make(),

                ViewAction::make()
                    ->label('Visualizar')
                    ->tooltip('Visualizar')
                    ->iconButton()
                    ->modalHeading(fn (OrganizationRecord $record): string => self::organizationModalHeading('Visualizar organização', $record))
                    ->modalWidth(Width::SevenExtraLarge),

                EditAction::make()
                    ->label('Editar')
                    ->tooltip('Editar')
                    ->iconButton()
                    ->modalHeading('Editar organização')
                    ->modalWidth(Width::SevenExtraLarge)
                    ->modalSubmitActionLabel('Salvar alterações')
                    ->successNotificationTitle('Organização atualizada')
                    ->mutateRecordDataUsing(function (array $data, OrganizationRecord $record): array {
                        return array_merge($data, [
                            'operational_phone' => $record->operational_phone,
                            'operational_email' => $record->operational_email,
                            'operational_postal_code' => $record->operational_postal_code,
                            'operational_street' => $record->operational_street,
                            'operational_number' => $record->operational_number,
                            'operational_complement' => $record->operational_complement,
                            'operational_district' => $record->operational_district,
                            'operational_city' => $record->operational_city,
                            'operational_state' => $record->operational_state,
                        ]);
                    })
                    ->using(function (OrganizationRecord $record, array $data): OrganizationRecord {
                        $operationalData = self::onlyOperationalData($data);
                        $organizationData = self::withoutOperationalData($data);

                        DB::transaction(function () use ($record, $organizationData, $operationalData): void {
                            $record->update($organizationData);

                            self::replaceOperationalAddress((string) $record->id, $operationalData);
                            self::replaceOperationalContacts((string) $record->id, $operationalData);
                        });

                        return $record->refresh();
                    })
                    ->extraModalFooterActions([
                        SyncOrganizationCnpjAction::make('syncOrganizationCnpjFromEditModal', iconButton: false),
                    ]),

                DeleteAction::make()
                    ->label('Excluir')
                    ->tooltip('Excluir')
                    ->iconButton()
                    ->modalHeading('Excluir organização')
                    ->modalDescription('A organização será movida para a lixeira e poderá ser restaurada posteriormente.')
                    ->modalSubmitActionLabel('Excluir')
                    ->successNotificationTitle('Organização excluída'),

                RestoreAction::make()
                    ->label('Restaurar')
                    ->tooltip('Restaurar')
                    ->iconButton()
                    ->modalHeading('Restaurar organização')
                    ->modalDescription('A organização voltará a aparecer normalmente na listagem.')
                    ->modalSubmitActionLabel('Restaurar')
                    ->successNotificationTitle('Organização restaurada'),

                ForceDeleteAction::make()
                    ->label('Excluir definitivamente')
                    ->tooltip('Excluir definitivamente')
                    ->iconButton()
                    ->modalHeading('Excluir organização definitivamente')
                    ->modalDescription('Esta ação não poderá ser desfeita.')
                    ->modalSubmitActionLabel('Excluir definitivamente')
                    ->successNotificationTitle('Organização excluída definitivamente'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function onlyOperationalData(array $data): array
    {
        return array_intersect_key($data, array_flip(self::OPERATIONAL_FORM_FIELDS));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function withoutOperationalData(array $data): array
    {
        $data = array_diff_key($data, array_flip(self::OPERATIONAL_FORM_FIELDS));

        unset($data['id']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function replaceOperationalAddress(string $organizationId, array $data): void
    {
        DB::table('organization_addresses')
            ->where('organization_id', $organizationId)
            ->where('source', self::SOURCE_OPERATIONAL_MANUAL)
            ->delete();

        $address = [
            'postal_code' => self::digits(self::clean($data['operational_postal_code'] ?? null)),
            'street' => self::clean($data['operational_street'] ?? null),
            'number' => self::clean($data['operational_number'] ?? null),
            'complement' => self::clean($data['operational_complement'] ?? null),
            'district' => self::clean($data['operational_district'] ?? null),
            'city' => self::clean($data['operational_city'] ?? null),
            'state' => self::state(self::clean($data['operational_state'] ?? null)),
        ];

        if (! self::hasAnyValue($address)) {
            return;
        }

        $now = now();

        DB::table('organization_addresses')->insert(self::withoutBlank([
            'organization_id' => $organizationId,
            'type' => 'operational',
            'label' => 'Endereço operacional',
            'postal_code' => $address['postal_code'],
            'street' => $address['street'],
            'number' => $address['number'],
            'complement' => $address['complement'],
            'district' => $address['district'],
            'city' => $address['city'],
            'state' => $address['state'],
            'country_code' => 'BR',
            'is_primary' => true,
            'source' => self::SOURCE_OPERATIONAL_MANUAL,
            'created_at' => $now,
            'updated_at' => $now,
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function replaceOperationalContacts(string $organizationId, array $data): void
    {
        DB::table('organization_contacts')
            ->where('organization_id', $organizationId)
            ->where('source', self::SOURCE_OPERATIONAL_MANUAL)
            ->whereIn('type', ['phone', 'telephone', 'mobile', 'email'])
            ->delete();

        $now = now();

        $phone = self::clean($data['operational_phone'] ?? null);

        if ($phone !== null) {
            DB::table('organization_contacts')->insert([
                'organization_id' => $organizationId,
                'type' => 'phone',
                'label' => 'Telefone operacional',
                'value' => $phone,
                'normalized_value' => self::digits($phone),
                'is_primary' => true,
                'is_verified' => false,
                'source' => self::SOURCE_OPERATIONAL_MANUAL,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $email = self::email(self::clean($data['operational_email'] ?? null));

        if ($email !== null) {
            DB::table('organization_contacts')->insert([
                'organization_id' => $organizationId,
                'type' => 'email',
                'label' => 'E-mail operacional',
                'value' => $email,
                'normalized_value' => $email,
                'is_primary' => true,
                'is_verified' => false,
                'source' => self::SOURCE_OPERATIONAL_MANUAL,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function hasAnyValue(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function withoutBlank(array $values): array
    {
        return array_filter(
            $values,
            fn (mixed $value): bool => $value !== null && $value !== '',
        );
    }

    private static function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function digits(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits === '' ? null : $digits;
    }

    private static function email(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strtolower($value);
    }

    private static function state(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strtoupper(substr($value, 0, 2));
    }

    private static function organizationModalHeading(string $prefix, OrganizationRecord $record): string
    {
        $parts = array_values(array_filter([
            $record->unit_code,
            $record->operational_name,
        ], fn (mixed $value): bool => filled($value)));

        if ($parts === []) {
            return $prefix;
        }

        return $prefix.' - '.implode(' - ', $parts);
    }

    private static function formatCnpj(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if (strlen($digits) !== 14) {
            return $value ?: '-';
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
