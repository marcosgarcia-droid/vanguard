<?php

namespace App\Modules\Identity\UI\Filament\Resources\TenantRecords\Schemas;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TenantRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                Section::make('Dados do grupo empresarial')
                    ->columns(6)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nome')
                            ->columnSpan(3),

                        TextEntry::make('legal_name')
                            ->label('Razão social')
                            ->placeholder('-')
                            ->columnSpan(3),

                        TextEntry::make('document')
                            ->label('Documento')
                            ->placeholder('-')
                            ->columnSpan(3),

                        TextEntry::make('status')
                            ->label('Status')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'active' => 'Ativo',
                                'inactive' => 'Inativo',
                                default => $state ?: '-',
                            })
                            ->badge()
                            ->columnSpan(3),

                        TextEntry::make('notes')
                            ->label('Observações')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Vínculos')
                    ->description('Usuários e organizações ligados a este grupo empresarial.')
                    ->columns(6)
                    ->schema([
                        TextEntry::make('users_list')
                            ->label('Usuários vinculados')
                            ->state(fn (TenantRecord $record): string => $record->users()->orderBy('name')->pluck('name')->join(', ') ?: '-')
                            ->columnSpan(3),

                        TextEntry::make('organizations_list')
                            ->label('Organizações vinculadas')
                            ->state(fn (TenantRecord $record): string => $record->organizations()->orderBy('legal_name')->pluck('legal_name')->join(', ') ?: '-')
                            ->columnSpan(3),

                        TextEntry::make('organizations_binding_notice')
                            ->label('Como o vínculo é gerenciado')
                            ->state('As organizações são vinculadas pelo cadastro de Organizações. Para alterar o grupo empresarial de uma unidade/CNPJ, acesse Cadastros > Organizações e edite o campo "Grupo empresarial" da organização correspondente.')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
