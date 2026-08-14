<?php

declare(strict_types=1);

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Actions;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeCommand;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeException;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeUseCase;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSubjectType;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoDerivativeRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class ReprocessEmployeeFacialPhotoDerivativeAction
{
    public static function make(): Action
    {
        return Action::make(
            'reprocessFacialPhotoDerivative'
        )
            ->label(
                'Reprocessar preparação'
            )
            ->tooltip(
                'Reprocessar preparação da foto facial'
            )
            ->icon(
                'heroicon-o-arrow-path'
            )
            ->iconButton()
            ->color('warning')
            ->requiresConfirmation()
            ->closeModalByClickingAway(false)
            ->modalHeading(
                'Reprocessar preparação da foto facial'
            )
            ->modalDescription(
                'Uma nova tentativa será enviada para a fila de processamento. '
                .'A foto original será preservada e nenhum comando será enviado '
                .'a leitores ou equipamentos de controle de acesso.'
            )
            ->modalSubmitActionLabel(
                'Solicitar reprocessamento'
            )
            ->visible(
                fn (
                    EmployeeRecord $record
                ): bool => self::isEligibleRecord(
                    $record
                )
                    && (
                        auth()->user()?->can(
                            'reprocessFacialPhotoDerivative',
                            $record
                        ) ?? false
                    )
            )
            ->action(
                function (
                    EmployeeRecord $record
                ): void {
                    $user = auth()->user();

                    if (! $user instanceof User) {
                        Notification::make()
                            ->title(
                                'Não foi possível identificar o operador'
                            )
                            ->body(
                                'Entre novamente no sistema antes de solicitar o reprocessamento.'
                            )
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Gate::authorize(
                        'reprocessFacialPhotoDerivative',
                        $record
                    );

                    try {
                        $result = app(
                            ReprocessFacialPhotoDerivativeUseCase::class
                        )->execute(
                            new ReprocessFacialPhotoDerivativeCommand(
                                subjectType: FacialPhotoSubjectType::Employee,
                                subjectId: (string) $record->getKey(),
                                operatorUserId: (int) $user->getKey(),
                                requestId: (string) Str::uuid(),
                            )
                        );

                        EmployeeFacialPhotoDerivativeReprocessingAudit::success(
                            employee: $record,
                            user: $user,
                            result: $result,
                        );

                        self::refreshFacialPhotoRelations(
                            $record
                        );

                        Notification::make()
                            ->title(
                                'Reprocessamento solicitado'
                            )
                            ->body(
                                'A preparação da foto facial foi enviada para a fila de processamento.'
                            )
                            ->success()
                            ->send();
                    } catch (
                        ReprocessFacialPhotoDerivativeException $exception
                    ) {
                        EmployeeFacialPhotoDerivativeReprocessingAudit::failure(
                            employee: $record,
                            user: $user,
                            exception: $exception,
                        );

                        self::refreshFacialPhotoRelations(
                            $record
                        );

                        Notification::make()
                            ->title(
                                'Não foi possível reprocessar a preparação'
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

    public static function isEligibleRecord(
        EmployeeRecord $record
    ): bool {
        if (! self::featureEnabled()) {
            return false;
        }

        $photo = self::latestPhoto(
            $record
        );

        if (
            ! $photo instanceof FacialPhotoRecord
            || ! $photo->isApproved()
            || ! self::hasValidSourceHash($photo)
        ) {
            return false;
        }

        $derivative = self::currentDerivative(
            $photo
        );

        if (
            ! $derivative instanceof FacialPhotoDerivativeRecord
        ) {
            return true;
        }

        $status = $derivative->status
            instanceof FacialPhotoDerivativeStatus
                ? $derivative->status
                : FacialPhotoDerivativeStatus::tryFrom(
                    (string) $derivative->status
                );

        return in_array(
            $status,
            [
                FacialPhotoDerivativeStatus::Pending,
                FacialPhotoDerivativeStatus::Failed,
            ],
            true
        );
    }

    private static function latestPhoto(
        EmployeeRecord $record
    ): ?FacialPhotoRecord {
        if (
            $record->relationLoaded(
                'latestFacialPhoto'
            )
        ) {
            $photo = $record->getRelation(
                'latestFacialPhoto'
            );

            return $photo instanceof FacialPhotoRecord
                ? $photo
                : null;
        }

        if (
            ! $record->exists
            || $record->getKey() === null
        ) {
            return null;
        }

        $photo = $record
            ->latestFacialPhoto()
            ->with(
                'derivatives.latestAttempt'
            )
            ->first();

        return $photo instanceof FacialPhotoRecord
            ? $photo
            : null;
    }

    private static function currentDerivative(
        FacialPhotoRecord $photo
    ): ?FacialPhotoDerivativeRecord {
        if (
            ! $photo->relationLoaded(
                'derivatives'
            )
        ) {
            if (
                ! $photo->exists
                || $photo->getKey() === null
            ) {
                return null;
            }

            $photo->loadMissing(
                'derivatives.latestAttempt'
            );
        }

        $derivatives = $photo->getRelation(
            'derivatives'
        );

        if (! $derivatives instanceof Collection) {
            return null;
        }

        $profile = (string) config(
            'facial_photos.normalization.default_profile',
            'vanguard_normalized'
        );

        $policyVersion = (string) config(
            'facial_photos.normalization.policy_version',
            'vanguard-normalization-v1'
        );

        $sourceSha256 = (string) $photo->sha256;

        $derivative = $derivatives
            ->filter(
                static fn (
                    mixed $candidate
                ): bool => $candidate instanceof FacialPhotoDerivativeRecord
                    && $candidate->profile
                        === $profile
                    && $candidate->policy_version
                        === $policyVersion
                    && $candidate->source_sha256
                        === $sourceSha256
            )
            ->sortByDesc(
                static fn (
                    FacialPhotoDerivativeRecord $candidate
                ): string => sprintf(
                    '%020d|%s',
                    $candidate->created_at
                        instanceof CarbonInterface
                            ? $candidate
                                ->created_at
                                ->getTimestamp()
                            : 0,
                    (string) $candidate->getKey()
                )
            )
            ->first();

        return $derivative
            instanceof FacialPhotoDerivativeRecord
                ? $derivative
                : null;
    }

    private static function hasValidSourceHash(
        FacialPhotoRecord $photo
    ): bool {
        return is_string(
            $photo->sha256
        )
            && preg_match(
                '/\A[a-f0-9]{64}\z/',
                $photo->sha256
            ) === 1;
    }

    private static function featureEnabled(): bool
    {
        return (bool) config(
            'facial_photos.normalization.enabled',
            false
        )
            && (bool) config(
                'facial_photos.normalization.async_generation.enabled',
                false
            );
    }

    private static function refreshFacialPhotoRelations(
        EmployeeRecord $record
    ): void {
        $record->unsetRelation(
            'latestFacialPhoto'
        );

        if (
            $record->exists
            && $record->getKey() !== null
        ) {
            $record->loadMissing(
                'latestFacialPhoto.derivatives.latestAttempt'
            );
        }
    }
}
