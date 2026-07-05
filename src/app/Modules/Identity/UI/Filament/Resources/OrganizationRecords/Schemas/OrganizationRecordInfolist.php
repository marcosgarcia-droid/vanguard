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
                Section::make('Dados cadastrais')
                    ->description('Identificação da unidade e dados oficiais vinculados ao CNPJ.')
                    ->columns(6)
                    ->schema([
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
                    ])
                    ->columnSpanFull(),

                Section::make('Dados operacionais')
                    ->description('Dados usados no dia a dia da unidade. Quando preenchidos, são priorizados na listagem e no contato principal.')
                    ->columns(6)
                    ->schema([
                        TextEntry::make('operational_phone')
                            ->label('Telefone operacional')
                            ->placeholder('Sem telefone operacional')
                            ->columnSpan(2),

                        TextEntry::make('operational_email')
                            ->label('E-mail operacional')
                            ->placeholder('Sem e-mail operacional')
                            ->columnSpan(2),

                        TextEntry::make('operational_city_state')
                            ->label('Cidade/UF operacional')
                            ->placeholder('Sem cidade/UF operacional')
                            ->columnSpan(2),

                        TextEntry::make('operational_address_line')
                            ->label('Endereço operacional')
                            ->placeholder('Sem endereço operacional')
                            ->columnSpan(4),

                        TextEntry::make('operational_postal_code')
                            ->label('CEP operacional')
                            ->placeholder('Sem CEP operacional')
                            ->columnSpan(2),
                    ])
                    ->columnSpanFull(),

                Section::make('Dados fiscais da consulta CNPJ')
                    ->description('Dados preservados da consulta cadastral. Eles continuam disponíveis mesmo quando houver dados operacionais.')
                    ->columns(6)
                    ->schema([
                        TextEntry::make('fiscal_phone_display')
                            ->label('Telefone fiscal')
                            ->placeholder('Não informado pela consulta')
                            ->columnSpan(2),

                        TextEntry::make('fiscal_email_display')
                            ->label('E-mail fiscal')
                            ->placeholder('Não informado pela consulta')
                            ->columnSpan(2),

                        TextEntry::make('fiscal_city_state')
                            ->label('Cidade/UF fiscal')
                            ->placeholder('Não informado pela consulta')
                            ->columnSpan(2),

                        TextEntry::make('fiscal_address_line')
                            ->label('Endereço fiscal')
                            ->placeholder('Não informado pela consulta')
                            ->columnSpan(4),

                        TextEntry::make('fiscal_postal_code')
                            ->label('CEP fiscal')
                            ->placeholder('Não informado pela consulta')
                            ->columnSpan(2),
                    ])
                    ->columnSpanFull(),

                Section::make('Atividades econômicas')
                    ->description('CNAEs retornados pela consulta CNPJ.')
                    ->columns(6)
                    ->schema([
                        TextEntry::make('primary_cnae_display')
                            ->label('CNAE principal')
                            ->placeholder('Não informado pela consulta')
                            ->columnSpanFull(),

                        TextEntry::make('secondary_cnaes_display')
                            ->label('CNAEs secundários')
                            ->placeholder('Nenhum CNAE secundário informado pela consulta')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Quadro societário')
                    ->description('Sócios e representantes retornados pela consulta CNPJ, quando disponíveis.')
                    ->columns(6)
                    ->schema([
                        TextEntry::make('members_display')
                            ->label('Sócios / representantes')
                            ->placeholder('Nenhum sócio ou representante informado pela consulta')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Regime tributário')
                    ->description('Informações tributárias retornadas pela consulta CNPJ.')
                    ->columns(6)
                    ->schema([
                        TextEntry::make('current_tax_regime_display')
                            ->label('Situação tributária')
                            ->placeholder('Regime tributário não informado pela consulta')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
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
