<?php

namespace App\Modules\Operations\Domain\FacialPhotos;

final class FacialPhotoStatusTransitionPolicy
{
    public function transition(
        FacialPhotoStatus $currentStatus,
        FacialPhotoValidationDecision $decision,
    ): FacialPhotoStatusTransition {
        if (
            $currentStatus
                !== FacialPhotoStatus::PendingValidation
        ) {
            throw FacialPhotoStatusTransitionException::statusNotEligible(
                $currentStatus
            );
        }

        $nextStatus = match ($decision) {
            FacialPhotoValidationDecision::Approved => FacialPhotoStatus::Approved,

            FacialPhotoValidationDecision::Rejected => FacialPhotoStatus::Rejected,

            FacialPhotoValidationDecision::Inconclusive => FacialPhotoStatus::PendingValidation,
        };

        return new FacialPhotoStatusTransition(
            from: $currentStatus,
            to: $nextStatus,
            decision: $decision,
        );
    }
}
