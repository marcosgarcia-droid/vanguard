<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitRecords\Schemas;

use App\Modules\Operations\Domain\Visits\VisitAuthorizationMethod;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Support\VanguardText;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class VisitRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                Tabs::make('Visualização da visita')
                    ->id('visit-record-infolist-tabs')
                    ->persistTab()
                    ->tabs([
                        Tab::make('Visita')
                            ->schema([
                                Section::make('Dados da visita')
                                    ->columns(6)
                                    ->schema([
                                        TextEntry::make('visitor.full_name')
                                            ->label('Visitante')
                                            ->formatStateUsing(
                                                fn (mixed $state): string => VanguardText::upper(
                                                    (string) $state
                                                )
                                            )
                                            ->columnSpan(3),

                                        TextEntry::make('organization.display_name')
                                            ->label('Unidade')
                                            ->state(
                                                fn ($record): string => VanguardText::upper(
                                                    $record->organization?->operational_name
                                                )
                                            )
                                            ->placeholder('-')
                                            ->columnSpan(3),

                                        TextEntry::make('hostEmployee.full_name')
                                            ->label('Visitado')
                                            ->formatStateUsing(
                                                fn (mixed $state): string => VanguardText::upper(
                                                    (string) $state
                                                )
                                            )
                                            ->placeholder('-')
                                            ->columnSpan(3),

                                        TextEntry::make('partner.display_name')
                                            ->label('Parceiro / empresa')
                                            ->formatStateUsing(
                                                fn (mixed $state): string => VanguardText::upper(
                                                    (string) $state
                                                )
                                            )
                                            ->placeholder('-')
                                            ->columnSpan(3),

                                        TextEntry::make('purpose')
                                            ->label('Finalidade')
                                            ->formatStateUsing(
                                                fn (mixed $state): string => VanguardText::upper(
                                                    (string) $state
                                                )
                                            )
                                            ->columnSpanFull(),

                                        TextEntry::make('expected_start_at')
                                            ->label('Início previsto')
                                            ->dateTime('d/m/Y H:i')
                                            ->columnSpan(2),

                                        TextEntry::make('expected_end_at')
                                            ->label('Término previsto')
                                            ->dateTime('d/m/Y H:i')
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('status')
                                            ->label('Situação')
                                            ->badge()
                                            ->formatStateUsing(
                                                fn (mixed $state): string => self::statusLabel(
                                                    $state
                                                )
                                            )
                                            ->columnSpan(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Operação')
                            ->schema([
                                Section::make('Chegada e identidade')
                                    ->columns(4)
                                    ->schema([
                                        TextEntry::make('arrived_at')
                                            ->label('Chegada')
                                            ->dateTime('d/m/Y H:i:s')
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('arrivedBy.name')
                                            ->label('Registrada por')
                                            ->placeholder('Automático / não informado')
                                            ->columnSpan(2),

                                        TextEntry::make('identity_verified_at')
                                            ->label('Identidade conferida em')
                                            ->dateTime('d/m/Y H:i:s')
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('identityVerifiedBy.name')
                                            ->label('Conferida por')
                                            ->placeholder('-')
                                            ->columnSpan(2),
                                    ])
                                    ->columnSpanFull(),

                                Section::make('Autorização')
                                    ->columns(4)
                                    ->schema([
                                        TextEntry::make('authorizerEmployee.full_name')
                                            ->label('Funcionário autorizador')
                                            ->formatStateUsing(
                                                fn (mixed $state): string => VanguardText::upper(
                                                    (string) $state
                                                )
                                            )
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('authorization_method')
                                            ->label('Meio da autorização')
                                            ->formatStateUsing(
                                                fn (mixed $state): string => self::authorizationMethodLabel(
                                                    $state
                                                )
                                            )
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('authorized_at')
                                            ->label('Autorizada em')
                                            ->dateTime('d/m/Y H:i:s')
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('authorizationRecordedBy.name')
                                            ->label('Registrada por')
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('authorization_notes')
                                            ->label('Observações da autorização')
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),

                                Section::make('Entrada e saída')
                                    ->columns(4)
                                    ->schema([
                                        TextEntry::make('checked_in_at')
                                            ->label('Entrada')
                                            ->dateTime('d/m/Y H:i:s')
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('checkedInBy.name')
                                            ->label('Entrada registrada por')
                                            ->placeholder('Automática / não informada')
                                            ->columnSpan(2),

                                        TextEntry::make('checked_out_at')
                                            ->label('Saída')
                                            ->dateTime('d/m/Y H:i:s')
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('checkedOutBy.name')
                                            ->label('Saída registrada por')
                                            ->placeholder('Automática / não informada')
                                            ->columnSpan(2),
                                    ])
                                    ->columnSpanFull(),

                                Section::make('Encerramento sem acesso')
                                    ->columns(4)
                                    ->schema([
                                        TextEntry::make('rejected_at')
                                            ->label('Não autorizada em')
                                            ->dateTime('d/m/Y H:i:s')
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('rejectedBy.name')
                                            ->label('Registrada por')
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('rejection_reason')
                                            ->label('Motivo da não autorização')
                                            ->placeholder('-')
                                            ->columnSpanFull(),

                                        TextEntry::make('cancelled_at')
                                            ->label('Cancelada em')
                                            ->dateTime('d/m/Y H:i:s')
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('cancelledBy.name')
                                            ->label('Cancelada por')
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('cancellation_reason')
                                            ->label('Motivo do cancelamento')
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Veículo')
                            ->visible(
                                fn ($record): bool => $record->vehicle !== null
                            )
                            ->schema([
                                Section::make('Veículo do visitante')
                                    ->description(
                                        'Dados informados no agendamento e situação da autorização de entrada.'
                                    )
                                    ->columns(6)
                                    ->schema([
                                        TextEntry::make('vehicle.plate')
                                            ->label('Placa')
                                            ->formatStateUsing(
                                                fn (mixed $state): string => VanguardText::upper(
                                                    (string) $state
                                                )
                                            )
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('vehicle.brand')
                                            ->label('Marca')
                                            ->formatStateUsing(
                                                fn (mixed $state): string => VanguardText::upper(
                                                    (string) $state
                                                )
                                            )
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('vehicle.model')
                                            ->label('Modelo')
                                            ->formatStateUsing(
                                                fn (mixed $state): string => VanguardText::upper(
                                                    (string) $state
                                                )
                                            )
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('vehicle.color')
                                            ->label('Cor')
                                            ->formatStateUsing(
                                                fn (mixed $state): string => VanguardText::upper(
                                                    (string) $state
                                                )
                                            )
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make(
                                            'vehicle.entry_authorized'
                                        )
                                            ->label(
                                                'Entrada do veículo'
                                            )
                                            ->badge()
                                            ->formatStateUsing(
                                                fn (
                                                    mixed $state
                                                ): string => filter_var(
                                                    $state,
                                                    FILTER_VALIDATE_BOOLEAN
                                                )
                                                    ? 'AUTORIZADA'
                                                    : 'NÃO AUTORIZADA'
                                            )
                                            ->color(
                                                fn (
                                                    mixed $state
                                                ): string => filter_var(
                                                    $state,
                                                    FILTER_VALIDATE_BOOLEAN
                                                )
                                                    ? 'success'
                                                    : 'warning'
                                            )
                                            ->columnSpan(2),

                                        TextEntry::make(
                                            'vehicle.entryAuthorizedBy.name'
                                        )
                                            ->label(
                                                'Autorizada por'
                                            )
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make(
                                            'vehicle.entry_authorized_at'
                                        )
                                            ->label(
                                                'Autorizada em'
                                            )
                                            ->dateTime(
                                                'd/m/Y H:i:s'
                                            )
                                            ->placeholder('-')
                                            ->columnSpan(2),
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
        $resolved = $status instanceof VisitStatus
            ? $status
            : VisitStatus::tryFrom((string) $status);

        return VanguardText::upper(
            $resolved?->label() ?: (string) $status
        );
    }

    private static function authorizationMethodLabel(
        mixed $method
    ): string {
        $resolved = $method instanceof VisitAuthorizationMethod
            ? $method
            : VisitAuthorizationMethod::tryFrom((string) $method);

        return $resolved?->label() ?: (string) $method;
    }
}
