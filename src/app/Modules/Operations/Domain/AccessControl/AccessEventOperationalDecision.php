<?php

namespace App\Modules\Operations\Domain\AccessControl;

enum AccessEventOperationalDecision: string
{
    case CheckInCandidate = 'check_in_candidate';
    case CheckOutCandidate = 'check_out_candidate';
    case ManualReview = 'manual_review';
    case NoAction = 'no_action';

    public function label(): string
    {
        return match ($this) {
            self::CheckInCandidate => 'Candidato a entrada',
            self::CheckOutCandidate => 'Candidato a saída',
            self::ManualReview => 'Revisão manual necessária',
            self::NoAction => 'Nenhuma ação necessária',
        };
    }

    public function isCandidate(): bool
    {
        return in_array($this, [
            self::CheckInCandidate,
            self::CheckOutCandidate,
        ], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $decision): array => [
                $decision->value => $decision->label(),
            ])
            ->all();
    }
}
