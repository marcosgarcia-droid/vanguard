<?php

namespace App\Modules\Identity\UI\Filament\Resources\PartnerRecords\Schemas;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class PartnerRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                Tabs::make('Visualização do parceiro')
                    ->id('partner-record-infolist-tabs')
                    ->persistTab()
                    ->tabs([
                        Tab::make('Parceiro')
                            ->schema([
                                Section::make('Dados principais')
                                    ->columns(6)
                                    ->schema([

                                        TextEntry::make('person_type')
                                            ->label('Tipo de pessoa')
                                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                                'company' => 'Jurídica',
                                                'individual' => 'Física',
                                                default => $state ?: '-',
                                            })
                                            ->columnSpan(2),

                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                                'active' => 'Ativo',
                                                'inactive' => 'Inativo',
                                                default => $state ?: '-',
                                            })
                                            ->badge()
                                            ->columnSpan(2),

                                        TextEntry::make('display_name')
                                            ->label('Parceiro')
                                            ->columnSpan(3),

                                        TextEntry::make('name')
                                            ->label('Nome / Razão social')
                                            ->columnSpan(3),

                                        TextEntry::make('organization.display_name')
                                            ->label('Unidade relacionada')
                                            ->placeholder('-')
                                            ->columnSpan(3),

                                        TextEntry::make('city_state')
                                            ->label('Cidade/UF')
                                            ->placeholder('-')
                                            ->columnSpan(3),

                                        TextEntry::make('primary_contact_display')
                                            ->label('Contato principal')
                                            ->placeholder('-')
                                            ->columnSpan(3),

                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Documentos')
                            ->schema([
                                Section::make('Documentos')
                                    ->schema([
                                        TextEntry::make('documents_summary')
                                            ->label('Documentos')
                                            ->state(fn (PartnerRecord $record): string => self::documentsSummary($record))
                                            ->placeholder('Nenhum documento cadastrado')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Contatos e endereços')
                            ->schema([
                                Section::make('Contatos')
                                    ->schema([
                                        TextEntry::make('contacts_summary')
                                            ->label('Contatos')
                                            ->state(fn (PartnerRecord $record): string => self::contactsSummary($record))
                                            ->placeholder('Nenhum contato cadastrado')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),

                                Section::make('Endereços')
                                    ->schema([
                                        TextEntry::make('addresses_summary')
                                            ->label('Endereços')
                                            ->state(fn (PartnerRecord $record): string => self::addressesSummary($record))
                                            ->placeholder('Nenhum endereço cadastrado')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Classificações')
                            ->schema([
                                Section::make('Classificações')
                                    ->schema([
                                        TextEntry::make('profiles')
                                            ->label('Perfis')
                                            ->formatStateUsing(fn (mixed $state): string => self::profilesDisplay($state))
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Observações')
                            ->schema([
                                Section::make('Observações')
                                    ->schema([
                                        TextEntry::make('notes')
                                            ->label('Observações')
                                            ->placeholder('Nenhuma observação registrada')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function documentsSummary(PartnerRecord $record): string
    {
        $record->loadMissing('documents');

        return $record->documents
            ->map(fn ($document): string => sprintf(
                '%s: %s%s',
                self::documentType($document->type),
                $document->number,
                $document->is_primary ? ' (principal)' : '',
            ))
            ->implode("\n") ?: 'Nenhum documento cadastrado';
    }

    private static function contactsSummary(PartnerRecord $record): string
    {
        $record->loadMissing('contacts');

        return $record->contacts
            ->map(fn ($contact): string => sprintf(
                '%s%s: %s%s',
                self::contactType($contact->type),
                $contact->label ? " ({$contact->label})" : '',
                $contact->value,
                $contact->is_primary ? ' (principal)' : '',
            ))
            ->implode("\n") ?: 'Nenhum contato cadastrado';
    }

    private static function addressesSummary(PartnerRecord $record): string
    {
        $record->loadMissing('addresses');

        return $record->addresses
            ->map(function ($address): string {
                $line = collect([
                    $address->street,
                    $address->number,
                    $address->district,
                    $address->city,
                    $address->state,
                    $address->postal_code ? self::formatCep($address->postal_code) : null,
                ])->filter()->implode(', ');

                return sprintf(
                    '%s: %s%s',
                    self::addressType($address->type),
                    $line ?: '-',
                    $address->is_primary ? ' (principal)' : '',
                );
            })
            ->implode("\n") ?: 'Nenhum endereço cadastrado';
    }

    private static function profilesDisplay(mixed $profiles): string
    {
        if (! is_array($profiles) || $profiles === []) {
            return '-';
        }

        return collect($profiles)
            ->map(fn (string $profile): string => match ($profile) {
                'customer' => 'Cliente',
                'supplier' => 'Fornecedor',
                'carrier' => 'Transportadora',
                'service_provider' => 'Prestador de serviço',
                'rural_producer' => 'Produtor rural',
                'other' => 'Outro',
                default => $profile,
            })
            ->implode(', ');
    }

    private static function documentType(?string $type): string
    {
        return match ($type) {
            'cnpj' => 'CNPJ',
            'cpf' => 'CPF',
            'state_registration' => 'Inscrição Estadual',
            'municipal_registration' => 'Inscrição Municipal',
            'rg' => 'RG',
            'other' => 'Outro',
            default => $type ?: '-',
        };
    }

    private static function contactType(?string $type): string
    {
        return match ($type) {
            'mobile' => 'Celular',
            'whatsapp' => 'WhatsApp',
            'phone' => 'Telefone',
            'email' => 'E-mail',
            'contact_person' => 'Pessoa de contato',
            'other' => 'Outro',
            default => $type ?: '-',
        };
    }

    private static function addressType(?string $type): string
    {
        return match ($type) {
            'fiscal' => 'Fiscal',
            'operational' => 'Operacional',
            'billing' => 'Cobrança',
            'delivery' => 'Entrega',
            'other' => 'Outro',
            default => $type ?: '-',
        };
    }

    private static function formatCep(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if (strlen($digits) !== 8) {
            return $value ?: '-';
        }

        return substr($digits, 0, 5).'-'.substr($digits, 5, 3);
    }
}
