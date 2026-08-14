<?php

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Schemas;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalIssue;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;

final class EmployeeFacialPhotoStatusPresentation
{
    /**
     * @return array{
     *     label: string,
     *     color: string
     * }
     */
    public static function summary(
        EmployeeRecord $record
    ): array {
        $record->loadMissing(
            'latestFacialPhoto'
        );

        return self::forStatus(
            $record->latestFacialPhoto?->status
        );
    }

    /**
     * @return list<string>
     */
    public static function feedback(
        EmployeeRecord $record
    ): array {
        if (
            ! $record->relationLoaded(
                'latestFacialPhoto'
            )
        ) {
            $record->loadMissing(
                'latestFacialPhoto'
            );
        }

        $photo = $record->latestFacialPhoto;

        if (
            $photo !== null
            && ! $photo->relationLoaded(
                'latestValidationAttempt'
            )
        ) {
            $photo->loadMissing(
                'latestValidationAttempt'
            );
        }

        if (
            $photo === null
            || ! in_array(
                $photo->status,
                [
                    FacialPhotoStatus::PendingValidation,
                    FacialPhotoStatus::Rejected,
                ],
                true
            )
        ) {
            return [];
        }

        $feedback = [];

        self::appendTechnicalFeedback(
            $feedback,
            $photo->rejection_reasons
        );

        self::appendValidationFeedback(
            $feedback,
            $photo
                ->latestValidationAttempt
                ?->issues
        );

        return array_values(
            $feedback
        );
    }

    public static function validationSummary(
        EmployeeRecord $record
    ): string {
        if (
            ! $record->relationLoaded(
                'latestFacialPhoto'
            )
        ) {
            $record->loadMissing(
                'latestFacialPhoto.latestValidationAttempt'
            );
        }

        $photo = $record->latestFacialPhoto;

        if ($photo === null) {
            return 'Não realizada';
        }

        $status = $photo->status instanceof FacialPhotoStatus
            ? $photo->status
            : FacialPhotoStatus::tryFrom(
                (string) $photo->status
            );

        $date = match ($status) {
            FacialPhotoStatus::Approved => $photo->approved_at,
            FacialPhotoStatus::Rejected => $photo->rejected_at,
            FacialPhotoStatus::Outdated => $photo->outdated_at,
            default => $photo->analyzed_at,
        };

        $label = match ($status) {
            FacialPhotoStatus::PendingValidation => 'Aguardando validação',
            FacialPhotoStatus::Approved => 'Aprovada',
            FacialPhotoStatus::Rejected => 'Reprovada',
            FacialPhotoStatus::Outdated => 'Foto desatualizada',
            default => 'Situação indisponível',
        };

        if (
            $date !== null
            && method_exists(
                $date,
                'format'
            )
        ) {
            return $label
                .' em '
                .$date->format('d/m/Y H:i');
        }

        return $label;
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

    /**
     * @param  array<string, string>  $feedback
     */
    private static function appendTechnicalFeedback(
        array &$feedback,
        mixed $codes
    ): void {
        if (! is_array($codes)) {
            return;
        }

        foreach ($codes as $code) {
            if (! is_string($code)) {
                continue;
            }

            $issue =
                FacialPhotoTechnicalIssue::tryFrom(
                    $code
                );

            if ($issue === null) {
                continue;
            }

            self::appendFeedbackLine(
                $feedback,
                $issue->label(),
                $issue->guidance()
            );
        }
    }

    /**
     * @param  array<string, string>  $feedback
     */
    private static function appendValidationFeedback(
        array &$feedback,
        mixed $codes
    ): void {
        if (! is_array($codes)) {
            return;
        }

        foreach ($codes as $code) {
            if (! is_string($code)) {
                continue;
            }

            $issue =
                FacialPhotoValidationIssue::tryFrom(
                    $code
                );

            if ($issue === null) {
                continue;
            }

            self::appendFeedbackLine(
                $feedback,
                $issue->label(),
                $issue->guidance()
            );
        }
    }

    /**
     * @param  array<string, string>  $feedback
     */
    private static function appendFeedbackLine(
        array &$feedback,
        string $label,
        string $guidance
    ): void {
        $line = sprintf(
            '%s — %s',
            $label,
            $guidance
        );

        $feedback[$line] = $line;
    }
}
