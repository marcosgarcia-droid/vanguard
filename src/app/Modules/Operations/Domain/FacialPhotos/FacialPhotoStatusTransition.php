<?php

namespace App\Modules\Operations\Domain\FacialPhotos;

use InvalidArgumentException;

final readonly class FacialPhotoStatusTransition
{
    public function __construct(
        public FacialPhotoStatus $from,
        public FacialPhotoStatus $to,
        public FacialPhotoValidationDecision $decision,
    ) {
        $this->validateConsistency();
    }

    public function changed(): bool
    {
        return $this->from !== $this->to;
    }

    public function remainsPendingValidation(): bool
    {
        return $this->to
            === FacialPhotoStatus::PendingValidation;
    }

    public function reachedTerminalStatus(): bool
    {
        return in_array(
            $this->to,
            [
                FacialPhotoStatus::Approved,
                FacialPhotoStatus::Rejected,
            ],
            true
        );
    }

    /**
     * @return array{
     *     from: string,
     *     from_label: string,
     *     to: string,
     *     to_label: string,
     *     decision: string,
     *     decision_label: string,
     *     changed: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from->value,
            'from_label' => $this->from->label(),
            'to' => $this->to->value,
            'to_label' => $this->to->label(),
            'decision' => $this->decision->value,
            'decision_label' => $this->decision->label(),
            'changed' => $this->changed(),
        ];
    }

    private function validateConsistency(): void
    {
        if (
            $this->from
                !== FacialPhotoStatus::PendingValidation
        ) {
            throw new InvalidArgumentException(
                'Uma transição de validação facial deve partir da situação aguardando validação.'
            );
        }

        $expectedStatus = match ($this->decision) {
            FacialPhotoValidationDecision::Approved => FacialPhotoStatus::Approved,

            FacialPhotoValidationDecision::Rejected => FacialPhotoStatus::Rejected,

            FacialPhotoValidationDecision::Inconclusive => FacialPhotoStatus::PendingValidation,
        };

        if ($this->to !== $expectedStatus) {
            throw new InvalidArgumentException(
                'A situação final não corresponde à decisão da validação facial.'
            );
        }
    }
}
