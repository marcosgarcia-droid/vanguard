<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Tables;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Support\ActivityLog\VanguardActivityLogTimelineAction;
use App\Support\VanguardText;
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
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VisitorRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                app(TenantContext::class)->applyTenantScope(
                    $query->with([
                        'tenant',
                        'organization',
                        'partner',
                        'documents',
                        'contacts',
                    ]),
                    auth()->user(),
                );

                app(TenantContext::class)->applyUserOrganizationScope(
                    $query,
                    auth()->user()
                );

                return $query;
            })
            ->defaultSort('full_name')
            ->columns([

                ViewColumn::make('full_name')
                    ->label('Nome')
                    ->view('filament.tables.columns.visitor-name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('document_display')
                    ->label('Documento')
                    ->state(
                        fn (VisitorRecord $record): string => self::documentDisplay($record)
                    )
                    ->placeholder('-'),

                TextColumn::make('organization.display_name')
                    ->label('Unidade')
                    ->state(
                        fn (VisitorRecord $record): string => VanguardText::upper(
                            $record->organization?->operational_name
                        )
                    )
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('partner.display_name')
                    ->label('Parceiro')
                    ->state(
                        fn (VisitorRecord $record): string => VanguardText::upper(
                            $record->partner?->display_name
                        )
                    )
                    ->placeholder('-'),

                TextColumn::make('primary_contact_display')
                    ->label('Contato')
                    ->state(
                        fn (VisitorRecord $record): string => self::contactDisplay($record)
                    )
                    ->placeholder('-'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (mixed $state): string => self::statusLabel($state)
                    )
                    ->color(
                        fn (mixed $state): string => self::statusColor($state)
                    )
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('organization_id')
                    ->label('Unidade')
                    ->options(fn (): array => self::organizationOptions())
                    ->searchable()
                    ->preload(),

                SelectFilter::make('partner_id')
                    ->label('Parceiro')
                    ->options(fn (): array => self::partnerOptions())
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(VisitorStatus::options()),

                TrashedFilter::make(),
            ])
            ->recordActions([
                VanguardActivityLogTimelineAction::make(),

                ViewAction::make()
                    ->label('Visualizar')
                    ->tooltip('Visualizar')
                    ->iconButton()
                    ->modalHeading(
                        fn (VisitorRecord $record): string => 'Visualizar visitante - '.$record->display_name
                    )
                    ->modalWidth(Width::SevenExtraLarge),

                EditAction::make()
                    ->label('Editar')
                    ->tooltip('Editar')
                    ->iconButton()
                    ->modalHeading(
                        fn (VisitorRecord $record): string => 'Editar visitante - '.$record->display_name
                    )
                    ->modalWidth(Width::SevenExtraLarge)
                    ->modalSubmitActionLabel('Salvar alterações')
                    ->successNotificationTitle('Visitante atualizado'),

                DeleteAction::make()
                    ->label('Excluir')
                    ->tooltip('Excluir')
                    ->iconButton()
                    ->modalHeading('Excluir visitante')
                    ->modalDescription('O visitante será movido para a lixeira e poderá ser restaurado posteriormente.')
                    ->modalSubmitActionLabel('Excluir')
                    ->successNotificationTitle('Visitante excluído'),

                RestoreAction::make()
                    ->label('Restaurar')
                    ->tooltip('Restaurar')
                    ->iconButton()
                    ->modalHeading('Restaurar visitante')
                    ->modalSubmitActionLabel('Restaurar')
                    ->successNotificationTitle('Visitante restaurado'),

                ForceDeleteAction::make()
                    ->label('Excluir definitivamente')
                    ->tooltip('Excluir definitivamente')
                    ->iconButton()
                    ->modalHeading('Excluir visitante definitivamente')
                    ->modalDescription('Esta ação não poderá ser desfeita.')
                    ->modalSubmitActionLabel('Excluir definitivamente')
                    ->successNotificationTitle('Visitante excluído definitivamente'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    private static function statusLabel(mixed $status): string
    {
        $resolved = $status instanceof VisitorStatus
            ? $status
            : VisitorStatus::tryFrom((string) $status);

        return VanguardText::upper(
            $resolved?->label() ?: (string) $status
        );
    }

    private static function statusColor(mixed $status): string
    {
        $resolved = $status instanceof VisitorStatus
            ? $status
            : VisitorStatus::tryFrom((string) $status);

        return match ($resolved) {
            VisitorStatus::Active => 'success',
            VisitorStatus::Inactive => 'gray',
            default => 'gray',
        };
    }

    private static function documentDisplay(
        VisitorRecord $record
    ): string {
        $document = $record->primaryDocument();

        if (! $document) {
            return '-';
        }

        $number = preg_replace(
            '/\D+/',
            '',
            (string) $document->number
        );

        if ($document->type === 'cpf' && strlen($number) === 11) {
            return substr($number, 0, 3)
                .'.'.substr($number, 3, 3)
                .'.'.substr($number, 6, 3)
                .'-'.substr($number, 9, 2);
        }

        return VanguardText::upper($document->number);
    }

    private static function contactDisplay(
        VisitorRecord $record
    ): string {
        $contact = $record->primaryContact();

        if (! $contact) {
            return '-';
        }

        if ($contact->type === 'email') {
            return (string) $contact->value;
        }

        $digits = preg_replace(
            '/\D+/',
            '',
            (string) $contact->value
        );

        if (strlen($digits) === 11) {
            return '('.substr($digits, 0, 2).') '
                .substr($digits, 2, 5)
                .'-'.substr($digits, 7);
        }

        if (strlen($digits) === 10) {
            return '('.substr($digits, 0, 2).') '
                .substr($digits, 2, 4)
                .'-'.substr($digits, 6);
        }

        return $contact->value ?: '-';
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
            ->orderBy('display_name');

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
                $organization->id => VanguardText::upper(
                    collect([
                        $organization->unit_code,
                        $organization->operational_name,
                    ])->filter()->implode(' - ')
                ),
            ])
            ->all();
    }

    private static function partnerOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $query = PartnerRecord::query()
            ->where('status', 'active')
            ->orderBy('trade_name')
            ->orderBy('name');

        app(TenantContext::class)->applyTenantScope(
            $query,
            $user
        );

        app(TenantContext::class)->applyUserOrganizationScope(
            $query,
            $user
        );

        return $query
            ->get()
            ->mapWithKeys(fn (PartnerRecord $partner): array => [
                $partner->id => VanguardText::upper(
                    $partner->display_name
                ),
            ])
            ->all();
    }
}
