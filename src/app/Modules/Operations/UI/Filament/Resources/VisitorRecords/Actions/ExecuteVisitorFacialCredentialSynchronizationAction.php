<?php

declare(strict_types=1);

namespace App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Actions;

use App\Models\User;
use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationCommand;
use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationResult;
use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationUseCase;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialCredentialSynchronizationRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class ExecuteVisitorFacialCredentialSynchronizationAction
{
    public const NAME =
        'executeFacialCredentialSynchronization';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label('Executar sincronização facial')
            ->icon('heroicon-o-play')
            ->color('warning')
            ->visible(
                static fn (
                    VisitorRecord $record
                ): bool => self::canDisplay(
                    $record
                )
            )
            ->schema(
                static fn (
                    VisitorRecord $record
                ): array => [
                    Select::make(
                        'synchronization_id'
                    )
                        ->label('Intenção pendente')
                        ->helperText(
                            'Selecione o leitor facial que receberá somente a resposta do simulador local.'
                        )
                        ->options(
                            VisitorFacialCredentialSynchronizationExecutionPresentation::options(
                                $record
                            )
                        )
                        ->required()
                        ->native(false)
                        ->searchable(),
                ]
            )
            ->requiresConfirmation()
            ->modalHeading(
                'Executar sincronização facial simulada'
            )
            ->modalDescription(
                'Esta ação executará explicitamente uma intenção pendente usando somente o simulador local configurado. Nenhuma comunicação física será realizada.'
            )
            ->modalSubmitActionLabel(
                'Executar no simulador'
            )
            ->modalCancelActionLabel(
                'Cancelar'
            )
            ->closeModalByClickingAway(false)
            ->action(
                static function (
                    VisitorRecord $record,
                    array $data
                ): void {
                    self::handle(
                        record: $record,
                        data: $data,
                    );
                }
            );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function handle(
        VisitorRecord $record,
        array $data
    ): void {
        $user = Auth::user();

        if (
            ! $user instanceof User
            || Gate::forUser($user)->denies(
                'executeFacialCredentialSynchronization',
                $record
            )
        ) {
            self::send(
                VisitorFacialCredentialSynchronizationExecutionPresentation::unavailable(
                    'Você não possui autorização para executar esta sincronização.'
                )
            );

            return;
        }

        $synchronizationId = is_string(
            $data['synchronization_id'] ?? null
        )
            ? trim($data['synchronization_id'])
            : '';

        try {
            $outcome = self::executeEligible(
                visitor: $record,
                synchronizationId: $synchronizationId,
                executor: app(
                    ExecuteFacialCredentialSynchronizationUseCase::class
                ),
            );
        } catch (Throwable $throwable) {
            VisitorFacialCredentialSynchronizationExecutionAudit::failure(
                visitor: $record,
                user: $user,
                synchronization: self::selectedSynchronization(
                    visitor: $record,
                    synchronizationId: $synchronizationId,
                ),
            );

            report($throwable);

            self::refresh(
                $record
            );

            self::send([
                'level' => 'danger',
                'title' => 'Não foi possível executar a sincronização',
                'body' => 'A execução foi interrompida de forma segura. Nenhuma comunicação física foi iniciada.',
            ]);

            return;
        }

        if (
            $outcome['result']
                instanceof ExecuteFacialCredentialSynchronizationResult
        ) {
            $synchronization =
                $outcome['synchronization']
                    ?? null;

            if (
                $synchronization
                    instanceof FacialCredentialSynchronizationRecord
            ) {
                VisitorFacialCredentialSynchronizationExecutionAudit::record(
                    visitor: $record,
                    user: $user,
                    synchronization: $synchronization,
                    result: $outcome['result'],
                );
            }

            self::refresh(
                $record
            );

            self::send(
                VisitorFacialCredentialSynchronizationExecutionPresentation::fromResult(
                    $outcome['result']
                )
            );

            return;
        }

        self::send(
            VisitorFacialCredentialSynchronizationExecutionPresentation::unavailable(
                $outcome['message']
            )
        );
    }

    /**
     * @return array{
     *     result: ExecuteFacialCredentialSynchronizationResult|null,
     *     synchronization: FacialCredentialSynchronizationRecord|null,
     *     message: string
     * }
     */
    public static function executeEligible(
        VisitorRecord $visitor,
        string $synchronizationId,
        ExecuteFacialCredentialSynchronizationUseCase $executor
    ): array {
        $environmentReason =
            VisitorFacialCredentialSynchronizationExecutionEnvironment::reason();

        if ($environmentReason !== null) {
            return [
                'result' => null,
                'synchronization' => null,
                'message' => $environmentReason,
            ];
        }

        $synchronization =
            VisitorFacialCredentialSynchronizationExecutionEligibility::resolve(
                $visitor,
                $synchronizationId
            );

        if ($synchronization === null) {
            return [
                'result' => null,
                'synchronization' => null,
                'message' => 'A intenção selecionada não está mais pendente ou não pertence ao contexto atual do visitante.',
            ];
        }

        $result = $executor->execute(
            new ExecuteFacialCredentialSynchronizationCommand(
                synchronizationId: (string) $synchronization->getKey()
            )
        );

        return [
            'result' => $result,
            'synchronization' => $synchronization,
            'message' => '',
        ];
    }

    private static function selectedSynchronization(
        VisitorRecord $visitor,
        string $synchronizationId
    ): ?FacialCredentialSynchronizationRecord {
        try {
            return VisitorFacialCredentialSynchronizationExecutionEligibility::resolve(
                $visitor,
                $synchronizationId
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return null;
        }
    }

    private static function refresh(
        VisitorRecord $record
    ): void {
        $record->unsetRelation(
            'facialCredentialSynchronizations'
        );

        if (
            $record->exists
            && $record->getKey() !== null
        ) {
            $record->load([
                'facialCredentialSynchronizations.accessDevice',
                'facialCredentialSynchronizations.latestAttempt',
            ]);
        }
    }

    private static function canDisplay(
        VisitorRecord $record
    ): bool {
        $user = Auth::user();

        return $user instanceof User
            && Gate::forUser($user)->allows(
                'executeFacialCredentialSynchronization',
                $record
            )
            && VisitorFacialCredentialSynchronizationExecutionEligibility::hasExecutable(
                $record
            )
            && VisitorFacialCredentialSynchronizationExecutionEnvironment::isReady();
    }

    /**
     * @param array{
     *     level: 'success'|'info'|'warning'|'danger',
     *     title: string,
     *     body: string
     * } $presentation
     */
    private static function send(
        array $presentation
    ): void {
        $notification = Notification::make()
            ->title(
                $presentation['title']
            )
            ->body(
                $presentation['body']
            );

        match ($presentation['level']) {
            'success' => $notification->success(),
            'info' => $notification->info(),
            'danger' => $notification->danger(),
            default => $notification->warning(),
        };

        $notification->send();
    }
}
