<?php

declare(strict_types=1);

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Actions;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeException;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeResult;
use Closure;
use Throwable;

final class EmployeeFacialPhotoDerivativeReprocessingAudit
{
    public static function success(
        EmployeeRecord $employee,
        User $user,
        ReprocessFacialPhotoDerivativeResult $result
    ): void {
        self::safely(
            static function () use (
                $employee,
                $user,
                $result
            ): void {
                activity('visitor_management')
                    ->causedBy($user)
                    ->performedOn($employee)
                    ->event(
                        'employee_facial_photo_derivative_reprocessing_requested'
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
        EmployeeRecord $employee,
        User $user,
        ReprocessFacialPhotoDerivativeException $exception
    ): void {
        self::safely(
            static function () use (
                $employee,
                $user,
                $exception
            ): void {
                activity('visitor_management')
                    ->causedBy($user)
                    ->performedOn($employee)
                    ->event(
                        'employee_facial_photo_derivative_reprocessing_failed'
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
