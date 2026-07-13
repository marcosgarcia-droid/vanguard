<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Schemas;

use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Support\VanguardText;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class VisitorRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                Tabs::make('Visualização do visitante')
                    ->id('visitor-record-infolist-tabs')
                    ->persistTab()
                    ->tabs([
                        Tab::make('Visitante')
                            ->schema([
                                Section::make('Dados principais')
                                    ->columns(6)
                                    ->schema([
                                        ImageEntry::make('photo_path')
                                            ->label('Foto')
                                            ->disk(
                                                fn (VisitorRecord $record): string => $record->photo_disk
                                                    ?: 'local'
                                            )
                                            ->visibility('private')
                                            ->columnSpan(1),

                                        TextEntry::make('full_name')
                                            ->label('Nome completo')
                                            ->formatStateUsing(
                                                fn (?string $state): string => VanguardText::upper($state)
                                            )
                                            ->columnSpan(3),

                                        TextEntry::make('preferred_name')
                                            ->label('Nome de uso')
                                            ->formatStateUsing(
                                                fn (?string $state): string => VanguardText::upper($state)
                                            )
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('birth_date')
                                            ->label('Data de nascimento')
                                            ->date('d/m/Y')
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->formatStateUsing(
                                                fn (mixed $state): string => self::statusLabel($state)
                                            )
                                            ->columnSpan(2),

                                        TextEntry::make('tenant.name')
                                            ->label('Grupo empresarial')
                                            ->formatStateUsing(
                                                fn (?string $state): string => VanguardText::upper($state)
                                            )
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('organization.display_name')
                                            ->label('Unidade')
                                            ->state(
                                                fn (VisitorRecord $record): string => VanguardText::upper(
                                                    $record->organization?->operational_name
                                                )
                                            )
                                            ->placeholder('-')
                                            ->columnSpan(3),

                                        TextEntry::make('partner.display_name')
                                            ->label('Parceiro / empresa representada')
                                            ->formatStateUsing(
                                                fn (?string $state): string => VanguardText::upper($state)
                                            )
                                            ->placeholder('-')
                                            ->columnSpan(3),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Documentos')
                            ->schema([
                                Section::make('Documentos')
                                    ->schema([
                                        TextEntry::make('documents_display')
                                            ->label('Documentos cadastrados')
                                            ->state(
                                                fn (VisitorRecord $record): string => self::documentsDisplay($record)
                                            )
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Contatos')
                            ->schema([
                                Section::make('Contatos')
                                    ->schema([
                                        TextEntry::make('contacts_display')
                                            ->label('Contatos cadastrados')
                                            ->state(
                                                fn (VisitorRecord $record): string => self::contactsDisplay($record)
                                            )
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

    private static function statusLabel(mixed $status): string
    {
        if ($status instanceof VisitorStatus) {
            return VanguardText::upper($status->label());
        }

        $resolved = VisitorStatus::tryFrom((string) $status);

        return VanguardText::upper(
            $resolved?->label() ?: (string) $status
        );
    }

    private static function documentsDisplay(
        VisitorRecord $record
    ): string {
        $record->loadMissing('documents');

        return $record->documents
            ->sortByDesc('is_primary')
            ->map(function ($document): string {
                $type = self::documentTypeLabel($document->type);
                $number = self::formatDocument(
                    $document->type,
                    $document->number
                );

                return collect([
                    $type,
                    $number,
                    $document->state,
                    $document->is_primary ? 'PRINCIPAL' : null,
                ])->filter()->implode(' - ');
            })
            ->filter()
            ->implode(PHP_EOL) ?: '-';
    }

    private static function contactsDisplay(
        VisitorRecord $record
    ): string {
        $record->loadMissing('contacts');

        return $record->contacts
            ->sortByDesc('is_primary')
            ->map(function ($contact): string {
                $label = VanguardText::upper(
                    $contact->label
                        ?: self::contactTypeLabel($contact->type)
                );

                return $label.': '.self::formatContact(
                    $contact->type,
                    $contact->value
                );
            })
            ->filter()
            ->implode(PHP_EOL) ?: '-';
    }

    private static function documentTypeLabel(?string $type): string
    {
        return match ($type) {
            'cpf' => 'CPF',
            'rg' => 'RG',
            'cnh' => 'CNH',
            'passport' => 'PASSAPORTE',
            'foreign_document' => 'DOCUMENTO ESTRANGEIRO',
            'other' => 'OUTRO',
            default => VanguardText::upper($type),
        };
    }

    private static function contactTypeLabel(?string $type): string
    {
        return match ($type) {
            'mobile' => 'CELULAR',
            'whatsapp' => 'WHATSAPP',
            'phone' => 'TELEFONE',
            'email' => 'E-MAIL',
            'emergency_phone' => 'TELEFONE DE EMERGÊNCIA',
            'other' => 'OUTRO',
            default => VanguardText::upper($type),
        };
    }

    private static function formatDocument(
        ?string $type,
        ?string $number
    ): string {
        $number = preg_replace('/\D+/', '', (string) $number);

        if ($type === 'cpf' && strlen($number) === 11) {
            return substr($number, 0, 3)
                .'.'.substr($number, 3, 3)
                .'.'.substr($number, 6, 3)
                .'-'.substr($number, 9, 2);
        }

        if ($type === 'cnpj' && strlen($number) === 14) {
            return substr($number, 0, 2)
                .'.'.substr($number, 2, 3)
                .'.'.substr($number, 5, 3)
                .'/'.substr($number, 8, 4)
                .'-'.substr($number, 12, 2);
        }

        return $number ?: '-';
    }

    private static function formatContact(
        ?string $type,
        ?string $value
    ): string {
        if ($type === 'email') {
            return (string) $value;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

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

        return $value ?: '-';
    }
}
