<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessDeviceRecords\Tables;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Operations\Application\AccessControl\AccessControlRuntime;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\UI\Filament\Resources\AccessDeviceRecords\Actions\ReadAccessDeviceConfigurationAction;
use App\Support\ActivityLog\VanguardActivityLogTimelineAction;
use App\Support\VanguardText;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccessDeviceRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                app(TenantContext::class)->applyTenantScope(
                    $query->with([
                        'tenant',
                        'organization',
                    ]),
                    auth()->user()
                );

                app(TenantContext::class)
                    ->applyUserOrganizationScope(
                        $query,
                        auth()->user()
                    );

                return $query;
            })
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->formatStateUsing(
                        fn (?string $state): string => VanguardText::upper(
                            $state
                        )
                    )
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Dispositivo')
                    ->formatStateUsing(
                        fn (?string $state): string => VanguardText::upper(
                            $state
                        )
                    )
                    ->searchable()
                    ->sortable(),

                TextColumn::make(
                    'organization.display_name'
                )
                    ->label('Unidade')
                    ->state(
                        fn (
                            AccessDeviceRecord $record
                        ): string => VanguardText::upper(
                            $record->organization
                                ?->operational_name
                        )
                    )
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('model')
                    ->label('Modelo')
                    ->placeholder('-'),

                TextColumn::make('connection')
                    ->label('Comunicação')
                    ->state(
                        fn (
                            AccessDeviceRecord $record
                        ): string => self::connectionDisplay(
                            $record
                        )
                    )
                    ->placeholder('-'),

                TextColumn::make('direction')
                    ->label('Direção')
                    ->badge()
                    ->formatStateUsing(
                        fn (
                            mixed $state
                        ): string => self::directionLabel($state)
                    )
                    ->color(
                        fn (
                            mixed $state
                        ): string => self::directionColor($state)
                    )
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (
                            mixed $state
                        ): string => self::statusLabel($state)
                    )
                    ->color(
                        fn (
                            mixed $state
                        ): string => self::statusColor($state)
                    )
                    ->sortable(),

                TextColumn::make('operational_mode')
                    ->label('Modo')
                    ->state(
                        fn (): string => app(
                            AccessControlRuntime::class
                        )->mode()->label()
                    )
                    ->badge()
                    ->color('warning'),

                TextColumn::make(
                    'last_communication_at'
                )
                    ->label('Última comunicação')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Não testado')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('organization_id')
                    ->label('Unidade')
                    ->options(
                        fn (): array => self::organizationOptions()
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make('direction')
                    ->label('Direção')
                    ->options(
                        AccessDeviceDirection::options()
                    ),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(
                        AccessDeviceStatus::options()
                    ),
            ])
            ->recordActions([
                ReadAccessDeviceConfigurationAction::make(),

                VanguardActivityLogTimelineAction::make(),

                ViewAction::make()
                    ->label('Visualizar')
                    ->tooltip('Visualizar')
                    ->iconButton()
                    ->modalHeading(
                        fn (
                            AccessDeviceRecord $record
                        ): string => 'Visualizar dispositivo - '
                            .$record->display_name
                    )
                    ->modalWidth(Width::SevenExtraLarge),

                EditAction::make()
                    ->label('Editar')
                    ->tooltip('Editar')
                    ->iconButton()
                    ->modalHeading(
                        fn (
                            AccessDeviceRecord $record
                        ): string => 'Editar dispositivo - '
                            .$record->display_name
                    )
                    ->modalWidth(Width::SevenExtraLarge)
                    ->modalSubmitActionLabel(
                        'Salvar alterações'
                    )
                    ->successNotificationTitle(
                        'Dispositivo atualizado'
                    ),
            ]);
    }

    private static function connectionDisplay(
        AccessDeviceRecord $record
    ): string {
        if (blank($record->ip_address)) {
            return '-';
        }

        return $record->ip_address
            .(
                $record->port
                    ? ':'.$record->port
                    : ''
            );
    }

    private static function directionLabel(mixed $state): string
    {
        $direction = $state instanceof AccessDeviceDirection
            ? $state
            : AccessDeviceDirection::tryFrom((string) $state);

        return VanguardText::upper(
            $direction?->label() ?: '-'
        );
    }

    private static function directionColor(mixed $state): string
    {
        $direction = $state instanceof AccessDeviceDirection
            ? $state
            : AccessDeviceDirection::tryFrom((string) $state);

        return match ($direction) {
            AccessDeviceDirection::Entry => 'success',
            AccessDeviceDirection::Exit => 'warning',
            AccessDeviceDirection::Bidirectional => 'info',
            default => 'gray',
        };
    }

    private static function statusLabel(mixed $state): string
    {
        $status = $state instanceof AccessDeviceStatus
            ? $state
            : AccessDeviceStatus::tryFrom((string) $state);

        return VanguardText::upper(
            $status?->label() ?: '-'
        );
    }

    private static function statusColor(mixed $state): string
    {
        $status = $state instanceof AccessDeviceStatus
            ? $state
            : AccessDeviceStatus::tryFrom((string) $state);

        return match ($status) {
            AccessDeviceStatus::Active => 'success',
            AccessDeviceStatus::Maintenance => 'warning',
            AccessDeviceStatus::Inactive => 'gray',
            default => 'gray',
        };
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
            ->orderBy('display_name');

        app(TenantContext::class)->applyOrganizationScope(
            $query,
            $user
        );

        app(TenantContext::class)
            ->applyUserOrganizationScope(
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
