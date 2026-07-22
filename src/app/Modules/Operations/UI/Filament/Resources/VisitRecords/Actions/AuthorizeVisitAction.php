<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Operations\Application\Visits\AuthorizeVisit\AuthorizeVisitCommand;
use App\Modules\Operations\Application\Visits\AuthorizeVisit\AuthorizeVisitUseCase;
use App\Modules\Operations\Application\Visits\VisitOperationException;
use App\Modules\Operations\Domain\Visits\VisitAuthorizationMethod;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

final class AuthorizeVisitAction
{
    public static function make(): Action
    {
        return Action::make('authorizeVisit')
            ->label('Autorizar')
            ->tooltip('Autorizar visita')
            ->icon('heroicon-o-check-circle')
            ->iconButton()
            ->color('success')
            ->closeModalByClickingAway(false)
            ->modalHeading('Autorizar visita')
            ->modalDescription(
                'Registre quem autorizou a visita e por qual meio a autorização foi recebida.'
            )
            ->modalSubmitActionLabel('Autorizar visita')
            ->form([
                Select::make('authorizer_employee_id')
                    ->label('Funcionário autorizador')
                    ->options(
                        fn (VisitRecord $record): array => self::employeeOptions(
                            $record
                        )
                    )
                    ->default(
                        fn (VisitRecord $record): ?string => $record->host_employee_id
                    )
                    ->required()
                    ->searchable()
                    ->preload()
                    ->native(false),

                Select::make('authorization_method')
                    ->label('Meio da autorização')
                    ->options(
                        VisitAuthorizationMethod::options()
                    )
                    ->default(
                        VisitAuthorizationMethod::Phone->value
                    )
                    ->required()
                    ->native(false),

                Textarea::make('authorization_notes')
                    ->label('Observações da autorização')
                    ->rows(4)
                    ->maxLength(2000),
            ])
            ->visible(
                fn (VisitRecord $record): bool => self::isEligible(
                    $record
                )
                    && (
                        auth()->user()?->can(
                            'operateGatehouse',
                            $record
                        ) ?? false
                    )
            )
            ->action(
                function (
                    VisitRecord $record,
                    array $data
                ): void {
                    $user = auth()->user();

                    if (! $user instanceof User) {
                        self::operatorNotFound();

                        return;
                    }

                    Gate::authorize(
                        'operateGatehouse',
                        $record
                    );

                    $method = VisitAuthorizationMethod::tryFrom(
                        (string) (
                            $data['authorization_method']
                            ?? ''
                        )
                    );

                    if (! $method instanceof VisitAuthorizationMethod) {
                        Notification::make()
                            ->title('Meio de autorização inválido')
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        app(
                            AuthorizeVisitUseCase::class
                        )->execute(
                            new AuthorizeVisitCommand(
                                visitId: $record->id,
                                authorizerEmployeeId: (string) (
                                    $data['authorizer_employee_id']
                                    ?? ''
                                ),
                                recordedByUserId: (int) $user->id,
                                method: $method,
                                notes: $data['authorization_notes']
                                    ?? null,
                            )
                        );

                        $record->refresh();

                        Notification::make()
                            ->title('Visita autorizada')
                            ->success()
                            ->send();
                    } catch (
                        VisitOperationException $exception
                    ) {
                        Notification::make()
                            ->title(
                                'Não foi possível autorizar a visita'
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

    public static function isEligible(
        VisitRecord $record
    ): bool {
        $status = $record->status;

        if (! $status instanceof VisitStatus) {
            $status = VisitStatus::tryFrom(
                (string) $status
            );
        }

        return $status?->canAuthorize() ?? false;
    }

    /**
     * @return array<string, string>
     */
    private static function employeeOptions(
        VisitRecord $record
    ): array {
        return EmployeeRecord::query()
            ->where(
                'tenant_id',
                $record->tenant_id
            )
            ->where('status', 'active')
            ->orderBy('full_name')
            ->pluck('full_name', 'id')
            ->all();
    }

    private static function operatorNotFound(): void
    {
        Notification::make()
            ->title(
                'Não foi possível identificar o operador'
            )
            ->danger()
            ->persistent()
            ->send();
    }
}
