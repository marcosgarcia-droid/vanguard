<?php

namespace App\Modules\Identity\UI\Filament\Resources\PartnerRecords\Tables;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\ClassificationOptionRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
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

class PartnerRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => app(TenantContext::class)
                ->applyTenantScope(
                    $query->with(['organization', 'documents', 'addresses', 'contacts']),
                    auth()->user(),
                ))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('display_name')
                    ->label('Parceiro')
                    ->searchable(['name', 'trade_name'])
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('name', $direction)),

                TextColumn::make('person_type')
                    ->label('Pessoa')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'company' => 'Jurídica',
                        'individual' => 'Física',
                        default => $state ?: '-',
                    })
                    ->badge(),

                TextColumn::make('profiles_display')
                    ->label('Perfis')
                    ->state(fn (PartnerRecord $record): string => self::profilesDisplay($record))
                    ->placeholder('-'),

                TextColumn::make('city_state')
                    ->label('Cidade/UF')
                    ->placeholder('-'),

                TextColumn::make('primary_contact_display')
                    ->label('Contato')
                    ->placeholder('-'),

                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'active' => 'Ativo',
                        'inactive' => 'Inativo',
                        default => $state ?: '-',
                    })
                    ->badge(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Visualizar')
                    ->tooltip('Visualizar')
                    ->iconButton()
                    ->modalHeading(fn (PartnerRecord $record): string => 'Visualizar parceiro - '.$record->display_name)
                    ->modalWidth(Width::SevenExtraLarge),

                EditAction::make()
                    ->label('Editar')
                    ->tooltip('Editar')
                    ->iconButton()
                    ->modalHeading(fn (PartnerRecord $record): string => 'Editar parceiro - '.$record->display_name)
                    ->modalWidth(Width::SevenExtraLarge)
                    ->modalSubmitActionLabel('Salvar alterações')
                    ->mutateRecordDataUsing(function (array $data, PartnerRecord $record): array {
                        $record->loadMissing('documents');

                        $data['official_document'] = $record->official_document_number;
                        $data['person_type'] = PartnerRecord::personTypeFromOfficialDocument($record->official_document_number)
                            ?: ($data['person_type'] ?? 'individual');

                        return $data;
                    })
                    ->using(function (PartnerRecord $record, array $data): PartnerRecord {
                        $officialDocument = $data['official_document'] ?? null;

                        $data['person_type'] = PartnerRecord::personTypeFromOfficialDocument($officialDocument)
                            ?: ($data['person_type'] ?? 'individual');

                        unset($data['official_document']);

                        DB::transaction(function () use ($record, $data, $officialDocument): void {
                            $record->update($data);
                            $record->syncOfficialDocument($officialDocument);
                        });

                        return $record->refresh();
                    })
                    ->successNotificationTitle('Parceiro atualizado'),

                DeleteAction::make()
                    ->label('Excluir')
                    ->tooltip('Excluir')
                    ->iconButton()
                    ->modalHeading('Excluir parceiro')
                    ->modalDescription('O parceiro será movido para a lixeira e poderá ser restaurado posteriormente.')
                    ->modalSubmitActionLabel('Excluir')
                    ->successNotificationTitle('Parceiro excluído'),

                RestoreAction::make()
                    ->label('Restaurar')
                    ->tooltip('Restaurar')
                    ->iconButton()
                    ->modalHeading('Restaurar parceiro')
                    ->modalDescription('O parceiro voltará a aparecer normalmente na listagem.')
                    ->modalSubmitActionLabel('Restaurar')
                    ->successNotificationTitle('Parceiro restaurado'),

                ForceDeleteAction::make()
                    ->label('Excluir definitivamente')
                    ->tooltip('Excluir definitivamente')
                    ->iconButton()
                    ->modalHeading('Excluir parceiro definitivamente')
                    ->modalDescription('Esta ação não poderá ser desfeita.')
                    ->modalSubmitActionLabel('Excluir definitivamente')
                    ->successNotificationTitle('Parceiro excluído definitivamente'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    private static function profilesDisplay(PartnerRecord $record): string
    {
        $profiles = $record->profiles;

        if (! is_array($profiles) || $profiles === []) {
            return '-';
        }

        $labels = ClassificationOptionRecord::query()
            ->where('tenant_id', $record->tenant_id)
            ->where('category', 'partner_profile')
            ->pluck('name', 'code')
            ->all();

        return collect($profiles)
            ->map(fn (string $profile): string => $labels[$profile] ?? $profile)
            ->implode(', ');
    }
}
