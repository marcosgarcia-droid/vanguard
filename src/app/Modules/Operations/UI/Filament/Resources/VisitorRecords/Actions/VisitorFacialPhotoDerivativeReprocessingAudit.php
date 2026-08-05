<?php

declare(strict_types=1);

namespace App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Actions;

use App\Models\User;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeException;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeResult;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Closure;
use Throwable;

final class VisitorFacialPhotoDerivativeReprocessingAudit
{
    public static function success(
        VisitorRecord $visitor,
        User $user,
        ReprocessFacialPhotoDerivativeResult $result
    ): void {
        self::safely(
            static function () use (
                $visitor,
                $user,
                $result
            ): void {
                activity('visitor_management')
                    ->causedBy($user)
                    ->performedOn($visitor)
                    ->event(
                        'visitor_facial_photo_derivative_reprocessing_requested'
                    )
                    ->withProperties([
                        'status' => 'success',
                        'scheduled' => $result->scheduled,
                        'previous_status' => $result->previousStatus?->value,
                        'previous_status_label' => $result->previousStatus?->label(),
                    ])
                    ->log(
                        'Reprocessamento da preparação facial solicitado'
                    );
            }
        );
    }

    public static function failure(
        VisitorRecord $visitor,
        User $user,
        ReprocessFacialPhotoDerivativeException $exception
    ): void {
        self::safely(
            static function () use (
                $visitor,
                $user,
                $exception
            ): void {
                activity('visitor_management')
                    ->causedBy($user)
                    ->performedOn($visitor)
                    ->event(
                        'visitor_facial_photo_derivative_reprocessing_failed'
                    )
                    ->withProperties([
                        'status' => 'failed',
                        'failure_code' => $exception->failureCode,
                        'message' => $exception->getMessage(),
                    ])
                    ->log(
                        'Falha no reprocessamento da preparação facial'
                    );
            }
        );
    }

    private static function safely(
        Closure $callback
    ): void {
        try {
            $callback();
        } catch (Throwable $throwable) {
            report(
                $throwable
            );
        }
    }
}
