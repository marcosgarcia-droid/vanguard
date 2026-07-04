<?php

namespace App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OrganizationRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                TextEntry::make('cnpj')
                    ->label('CNPJ')
                    ->formatStateUsing(fn (?string $state): string => self::formatCnpj($state))
                    ->placeholder('-')
                    ->columnSpan(3),

                TextEntry::make('tax_registration_status_name')
                    ->label('Situação cadastral')
                    ->placeholder('-')
                    ->columnSpan(3),

                TextEntry::make('legal_name')
                    ->label('Razão social')
                    ->columnSpan(3),

                TextEntry::make('trade_name')
                    ->label('Nome fantasia')
                    ->placeholder('-')
                    ->columnSpan(3),

                TextEntry::make('establishment_type')
                    ->label('Tipo de estabelecimento')
                    ->placeholder('-')
                    ->columnSpan(3),

                TextEntry::make('legal_nature_name')
                    ->label('Natureza jurídica')
                    ->placeholder('-')
                    ->columnSpan(3),

                TextEntry::make('opened_at')
                    ->label('Data de abertura')
                    ->date()
                    ->placeholder('-')
                    ->columnSpan(2),

                TextEntry::make('tax_registration_status_date')
                    ->label('Data da situação cadastral')
                    ->date()
                    ->placeholder('-')
                    ->columnSpan(2),

                TextEntry::make('share_capital')
                    ->label('Capital social')
                    ->money('BRL')
                    ->placeholder('-')
                    ->columnSpan(2),

                IconEntry::make('is_head_office')
                    ->label('Matriz')
                    ->boolean()
                    ->placeholder('-')
                    ->columnSpan(2),

                TextEntry::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'active' => 'Ativa',
                        'inactive' => 'Inativa',
                        default => $state ?: '-',
                    })
                    ->columnSpan(2),

                TextEntry::make('company_size_name')
                    ->label('Porte')
                    ->placeholder('-')
                    ->columnSpan(2),

                TextEntry::make('notes')
                    ->label('Observações')
                    ->placeholder('-')
                    ->columnSpanFull(),
            ]);
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
