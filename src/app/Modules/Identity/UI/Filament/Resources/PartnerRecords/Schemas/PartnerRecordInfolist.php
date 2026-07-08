<?php

namespace App\Modules\Identity\UI\Filament\Resources\PartnerRecords\Schemas;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\ClassificationOptionRecord;
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
                    ->tabs([
                        Tab::make('Parceiro')
                            ->schema([
                                Section::make('Dados principais')
                                    ->columns(6)
                                    ->schema([
                                        TextEntry::make('official_document_number')
                                            ->label('Documento oficial')
                                            ->placeholder('-')
                                            ->columnSpan(3),

                                        TextEntry::make('person_type')
                                            ->label('Tipo de pessoa')
                                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                                'individual' => 'Física',
                                                'company' => 'Jurídica',
                                                default => $state ?: '-',
                                            })
                                            ->badge()
                                            ->columnSpan(3),

                                        TextEntry::make('name')
                                            ->label('Nome / Razão social')
                                            ->columnSpan(3),

                                        TextEntry::make('trade_name')
                                            ->label('Nome fantasia / Apelido')
                                            ->placeholder('-')
                                            ->columnSpan(3),

                                        TextEntry::make('profiles_display')
                                            ->label('Perfis do parceiro')
                                            ->state(fn (PartnerRecord $record): string => self::profilesDisplay($record))
                                            ->placeholder('-')
                                            ->columnSpanFull(),

                                        TextEntry::make('organization.display_name')
                                            ->label('Unidade relacionada')
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
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Outros documentos')
                            ->schema([
                                Section::make('Outros documentos')
                                    ->schema([
                                        TextEntry::make('other_documents_display')
                                            ->label('Documentos secundários')
                                            ->state(fn (PartnerRecord $record): string => self::otherDocumentsDisplay($record))
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Contatos e endereços')
                            ->schema([
                                Section::make('Contatos')
                                    ->schema([
                                        TextEntry::make('contacts_display')
                                            ->label('Contatos')
                                            ->state(fn (PartnerRecord $record): string => self::contactsDisplay($record))
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),

                                Section::make('Endereços')
                                    ->schema([
                                        TextEntry::make('addresses_display')
                                            ->label('Endereços')
                                            ->state(fn (PartnerRecord $record): string => self::addressesDisplay($record))
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
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
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

    private static function otherDocumentsDisplay(PartnerRecord $record): string
    {
        $record->loadMissing('documents');

        return $record->documents
            ->whereNotIn('type', PartnerRecord::OFFICIAL_DOCUMENT_TYPES)
            ->map(fn ($document): string => collect([
                strtoupper((string) $document->type),
                $document->number,
                $document->state,
            ])->filter()->implode(' - '))
            ->filter()
            ->implode(PHP_EOL) ?: '-';
    }

    private static function contactsDisplay(PartnerRecord $record): string
    {
        $record->loadMissing('contacts');

        return $record->contacts
            ->map(fn ($contact): string => collect([
                $contact->label ?: $contact->type,
                $contact->value,
            ])->filter()->implode(': '))
            ->filter()
            ->implode(PHP_EOL) ?: '-';
    }

    private static function addressesDisplay(PartnerRecord $record): string
    {
        $record->loadMissing('addresses');

        return $record->addresses
            ->map(fn ($address): string => collect([
                $address->street,
                $address->number,
                $address->district,
                collect([$address->city, $address->state])->filter()->implode('/'),
            ])->filter()->implode(', '))
            ->filter()
            ->implode(PHP_EOL) ?: '-';
    }
}
