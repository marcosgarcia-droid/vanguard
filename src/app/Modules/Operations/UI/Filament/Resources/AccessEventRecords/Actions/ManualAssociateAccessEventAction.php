<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions;

use App\Models\User;
use App\Modules\Operations\Application\AccessControl\Events\ManualAssociate\ManualAssociateAccessEventCommand;
use App\Modules\Operations\Application\AccessControl\Events\ManualAssociate\ManualAssociateAccessEventException;
use App\Modules\Operations\Application\AccessControl\Events\ManualAssociate\ManualAssociateAccessEventResult;
use App\Modules\Operations\Application\AccessControl\Events\ManualAssociate\ManualAssociateAccessEventUseCase;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Support\VanguardText;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class ManualAssociateAccessEventAction
{
    public static function make(): Action
    {
        return Action::make(
            'manualAssociateAccessEvent'
        )
            ->label('Associar manualmente')
            ->tooltip('Associar manualmente')
            ->icon('heroicon-o-link')
            ->iconButton()
            ->color('info')
            ->closeModalByClickingAway(false)
            ->modalHeading(
                'Associar evento manualmente'
            )
            ->modalDescription(
                'Selecione o visitante e, quando aplicável, uma visita compatível. Esta ação associa o evento, mas não registra entrada, saída nem envia comandos ao dispositivo.'
            )
            ->modalSubmitActionLabel(
                'Confirmar associação'
            )
            ->form([
                Select::make('visitor_id')
                    ->label('Visitante')
                    ->options(
                        fn (
                            AccessEventRecord $record
                        ): array => self::visitorOptions(
                            $record
                        )
                    )
                    ->default(
                        fn (
                            AccessEventRecord $record
                        ): ?string => $record->visitor_id
                    )
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(
                        function (Set $set): void {
                            $set('visit_id', null);
                        }
                    )
                    ->helperText(
                        'São apresentados somente visitantes ativos da mesma unidade do evento.'
                    ),

                Select::make('visit_id')
                    ->label('Visita')
                    ->options(
                        fn (
                            Get $get,
                            AccessEventRecord $record
                        ): array => self::visitOptions(
                            $record,
                            self::nullableString(
                                $get('visitor_id')
                            )
                        )
                    )
                    ->default(
                        fn (
                            AccessEventRecord $record
                        ): ?string => $record->visit_id
                    )
                    ->searchable()
                    ->preload()
                    ->disabled(
                        fn (
                            Get $get
                        ): bool => blank(
                            $get('visitor_id')
                        )
                    )
                    ->placeholder(
                        'Não associar uma visita agora'
                    )
                    ->helperText(
                        'Na entrada, somente visitas autorizadas. Na saída, somente visitas em andamento.'
                    ),

                Textarea::make('reason')
                    ->label('Justificativa')
                    ->required()
                    ->rows(4)
                    ->maxLength(1000)
                    ->helperText(
                        'Informe como a identidade e o vínculo foram confirmados.'
                    ),

                Hidden::make('idempotency_key')
                    ->default(
                        fn (): string => (string) Str::uuid()
                    )
                    ->required(),
            ])
            ->visible(
                fn (
                    AccessEventRecord $record
                ): bool => self::isPendingAssociation(
                    $record
                )
                    && (
                        auth()->user()?->can(
                            'associateManually',
                            $record
                        ) ?? false
                    )
            )
            ->action(
                function (
                    AccessEventRecord $record,
                    array $data
                ): void {
                    $user = auth()->user();

                    if (! $user instanceof User) {
                        Notification::make()
                            ->title(
                                'Não foi possível identificar o operador'
                            )
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Gate::authorize(
                        'associateManually',
                        $record
                    );

                    try {
                        $result = app(
                            ManualAssociateAccessEventUseCase::class
                        )->execute(
                            new ManualAssociateAccessEventCommand(
                                eventId: $record->id,
                                visitorId: (string) (
                                    $data['visitor_id']
                                    ?? ''
                                ),
                                visitId: self::nullableString(
                                    $data['visit_id']
                                    ?? null
                                ),
                                operatorUserId: (int) $user->id,
                                reason: (string) (
                                    $data['reason']
                                    ?? ''
                                ),
                                idempotencyKey: (string) (
                                    $data['idempotency_key']
                                    ?? ''
                                ),
                            )
                        );

                        $record
                            ->refresh()
                            ->load([
                                'visitor',
                                'visit',
                            ]);

                        self::auditSuccess(
                            $record,
                            $user,
                            $result,
                            (string) $data['reason']
                        );

                        self::sendSuccessNotification(
                            $result
                        );
                    } catch (
                        ManualAssociateAccessEventException $exception
                    ) {
                        self::auditFailure(
                            $record,
                            $user,
                            $exception
                        );

                        Notification::make()
                            ->title(
                                'Não foi possível associar o evento'
                            )
                            ->body(
                                $exception->getMessage()
                            )
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }
            );
    }

    /**
     * @return array<string, string>
     */
    public static function visitorOptions(
        AccessEventRecord $record
    ): array {
        if (
            blank($record->tenant_id)
            || blank($record->organization_id)
        ) {
            return [];
        }

        return VisitorRecord::query()
            ->where(
                'tenant_id',
                $record->tenant_id
            )
            ->where(
                'organization_id',
                $record->organization_id
            )
            ->where(
                'status',
                VisitorStatus::Active->value
            )
            ->orderBy('full_name')
            ->get()
            ->mapWithKeys(
                fn (
                    VisitorRecord $visitor
                ): array => [
                    $visitor->id => VanguardText::upper(
                        $visitor->display_name
                    ),
                ]
            )
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function visitOptions(
        AccessEventRecord $record,
        ?string $visitorId
    ): array {
        if (
            blank($record->tenant_id)
            || blank($record->organization_id)
            || blank($visitorId)
        ) {
            return [];
        }

        $status = self::eligibleVisitStatus(
            $record
        );

        if (! $status instanceof VisitStatus) {
            return [];
        }

        return VisitRecord::query()
            ->where(
                'tenant_id',
                $record->tenant_id
            )
            ->where(
                'organization_id',
                $record->organization_id
            )
            ->where(
                'visitor_id',
                $visitorId
            )
            ->where(
                'status',
                $status->value
            )
            ->orderBy('expected_start_at')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(
                fn (
                    VisitRecord $visit
                ): array => [
                    $visit->id => self::visitLabel(
                        $visit
                    ),
                ]
            )
            ->all();
    }

    private static function isPendingAssociation(
        AccessEventRecord $record
    ): bool {
        $status = $record->status;

        if ($status instanceof AccessEventStatus) {
            return $status
                === AccessEventStatus::PendingAssociation;
        }

        return AccessEventStatus::tryFrom(
            (string) $status
        ) === AccessEventStatus::PendingAssociation;
    }

    private static function eligibleVisitStatus(
        AccessEventRecord $record
    ): ?VisitStatus {
        $direction = $record->direction;

        if (! $direction instanceof AccessEventDirection) {
            $direction = AccessEventDirection::tryFrom(
                (string) $direction
            );
        }

        return match ($direction) {
            AccessEventDirection::Entry => VisitStatus::Authorized,

            AccessEventDirection::Exit => VisitStatus::InProgress,

            default => null,
        };
    }

    private static function visitLabel(
        VisitRecord $visit
    ): string {
        $status = $visit->status;

        if (! $status instanceof VisitStatus) {
            $status = VisitStatus::tryFrom(
                (string) $status
            );
        }

        return VanguardText::upper(
            collect([
                $visit->expected_start_at
                    ?->format('d/m/Y H:i'),
                $visit->purpose,
                $status?->label(),
            ])
                ->filter()
                ->implode(' - ')
        );
    }

    private static function sendSuccessNotification(
        ManualAssociateAccessEventResult $result
    ): void {
        if ($result->duplicate) {
            Notification::make()
                ->title(
                    'Associação manual já registrada'
                )
                ->body(
                    'A solicitação já havia sido concluída e nenhuma associação duplicada foi criada.'
                )
                ->success()
                ->send();

            return;
        }

        if (
            $result->status
            === AccessEventStatus::Processed
        ) {
            Notification::make()
                ->title(
                    'Evento associado manualmente'
                )
                ->body(
                    'O visitante e a visita foram associados. A situação operacional da visita não foi alterada.'
                )
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title(
                'Visitante associado ao evento'
            )
            ->body(
                'O evento permanece aguardando a seleção de uma visita compatível.'
            )
            ->warning()
            ->send();
    }

    private static function auditSuccess(
        AccessEventRecord $record,
        User $user,
        ManualAssociateAccessEventResult $result,
        string $reason
    ): void {
        activity('access_control')
            ->causedBy($user)
            ->performedOn($record)
            ->event(
                'access_event_manually_associated'
            )
            ->withProperties([
                'status' => 'success',
                'association_id' => $result->associationId,
                'resulting_status' => $result->status->value,
                'result_code' => $result->resultCode,
                'visitor_id' => $result->visitorId,
                'visitor_name' => $record->visitor?->display_name,
                'visit_id' => $result->visitId,
                'visit_reference' => $record->visit
                        ? self::visitLabel(
                            $record->visit
                        )
                        : null,
                'reason' => trim($reason),
                'duplicate' => $result->duplicate,
                'message' => $result->visitId === null
                    ? 'Visitante associado manualmente; o evento permanece aguardando uma visita.'
                    : 'Evento associado manualmente ao visitante e à visita.',
            ])
            ->log(
                'Associação manual do evento de acesso'
            );
    }

    private static function auditFailure(
        AccessEventRecord $record,
        User $user,
        ManualAssociateAccessEventException $exception
    ): void {
        activity('access_control')
            ->causedBy($user)
            ->performedOn($record)
            ->event(
                'access_event_manually_associated'
            )
            ->withProperties([
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ])
            ->log(
                'Falha na associação manual do evento de acesso'
            );
    }

    private static function nullableString(
        mixed $value
    ): ?string {
        $value = trim((string) $value);

        return $value !== ''
            ? $value
            : null;
    }
}
