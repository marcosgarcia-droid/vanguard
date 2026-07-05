<?php

namespace App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Schemas;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrganizationRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                TextEntry::make('operational_name')
                    ->label('Unidade')
                    ->columnSpan(3),

                TextEntry::make('unit_code')
                    ->label('Código da unidade')
                    ->placeholder('-')
                    ->columnSpan(3),

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

                TextEntry::make('city_state')
                    ->label('Cidade/UF')
                    ->placeholder('-')
                    ->columnSpan(2),

                TextEntry::make('primary_address_line')
                    ->label('Endereço principal')
                    ->placeholder('-')
                    ->columnSpan(4),

                TextEntry::make('primary_postal_code')
                    ->label('CEP')
                    ->placeholder('-')
                    ->columnSpan(2),

                TextEntry::make('primary_phone_display')
                    ->label('Telefone')
                    ->placeholder('-')
                    ->columnSpan(2),

                TextEntry::make('primary_email_display')
                    ->label('E-mail')
                    ->placeholder('-')
                    ->columnSpan(2),

                Section::make('Sincronização CNPJ')
                    ->description('Histórico resumido da última consulta cadastral realizada para esta organização.')
                    ->columns(6)
                    ->schema([
                        TextEntry::make('latestCnpjSync.requested_at')
                            ->label('Última consulta')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Nenhuma consulta registrada')
                            ->columnSpan(2),

                        TextEntry::make('latestCnpjSync.status')
                            ->label('Status')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'success' => 'Sucesso',
                                'failed' => 'Falha',
                                default => '-',
                            })
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'success' => 'success',
                                'failed' => 'danger',
                                default => 'gray',
                            })
                            ->placeholder('-')
                            ->columnSpan(1),

                        TextEntry::make('latestCnpjSync.provider')
                            ->label('Provider')
                            ->formatStateUsing(fn (?string $state): string => self::formatProvider($state))
                            ->placeholder('-')
                            ->columnSpan(1),

                        TextEntry::make('cnpj_sync_attempts_count')
                            ->label('Tentativas registradas')
                            ->state(fn (OrganizationRecord $record): int => $record->cnpjSyncs()->count())
                            ->placeholder('-')
                            ->columnSpan(1),

                        TextEntry::make('latestCnpjSync.duration_ms')
                            ->label('Duração')
                            ->formatStateUsing(fn (?int $state): string => $state === null ? '-' : "{$state} ms")
                            ->placeholder('-')
                            ->columnSpan(1),

                        TextEntry::make('latestCnpjSync.error_message')
                            ->label('Mensagem')
                            ->state(fn (OrganizationRecord $record): string => self::friendlySyncMessage($record))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

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

    private static function formatProvider(?string $provider): string
    {
        return match ($provider) {
            'brasilapi' => 'BrasilAPI',
            'receitaws' => 'ReceitaWS',
            'failover-cnpj' => 'Failover CNPJ',
            default => $provider ?: '-',
        };
    }

    private static function friendlySyncMessage(OrganizationRecord $record): string
    {
        $sync = $record->latestCnpjSync;

        if ($sync === null) {
            return 'Nenhuma consulta CNPJ registrada para esta organização.';
        }

        if ($sync->status === 'success') {
            return 'Consulta concluída com sucesso.';
        }

        if ($sync->http_status !== null) {
            return "Consulta falhou com resposta HTTP {$sync->http_status}.";
        }

        return 'Não foi possível consultar o serviço de CNPJ no momento. Verifique conexão, DNS ou disponibilidade do provider.';
    }
}
