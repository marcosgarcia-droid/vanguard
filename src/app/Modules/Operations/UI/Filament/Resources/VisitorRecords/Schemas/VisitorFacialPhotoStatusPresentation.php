<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Schemas;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;

final class VisitorFacialPhotoStatusPresentation
{
    /**
     * @return array{
     *     label: string,
     *     color: string
     * }
     */
    public static function summary(
        VisitorRecord $record
    ): array {
        $record->loadMissing(
            'latestFacialPhoto'
        );

        return self::forStatus(
            $record->latestFacialPhoto?->status
        );
    }

    /**
     * @return array{
     *     label: string,
     *     color: string
     * }
     */
    public static function forStatus(
        FacialPhotoStatus|string|null $status
    ): array {
        if (is_string($status)) {
            $status =
                FacialPhotoStatus::tryFrom($status);
        }

        return match ($status) {
            FacialPhotoStatus::PendingValidation => [
                'label' => $status->label(),
                'color' => 'warning',
            ],
            FacialPhotoStatus::Approved => [
                'label' => $status->label(),
                'color' => 'success',
            ],
            FacialPhotoStatus::Rejected => [
                'label' => $status->label(),
                'color' => 'danger',
            ],
            FacialPhotoStatus::Outdated => [
                'label' => $status->label(),
                'color' => 'gray',
            ],
            default => [
                'label' => 'Não cadastrada',
                'color' => 'gray',
            ],
        };
    }
}
