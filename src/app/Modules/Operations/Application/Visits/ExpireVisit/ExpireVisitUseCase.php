<?php

namespace App\Modules\Operations\Application\Visits\ExpireVisit;

use App\Modules\Operations\Application\Visits\VisitOperationException;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Support\Contracts\TransactionManager;
use App\Support\Contracts\UseCase;
use Carbon\CarbonImmutable;

final readonly class ExpireVisitUseCase implements UseCase
{
    public function __construct(
        private TransactionManager $transactions,
    ) {}

    /**
     * @return list<VisitStatus>
     */
    public static function eligibleStatuses(): array
    {
        return [
            VisitStatus::Scheduled,
            VisitStatus::PendingAuthorization,
            VisitStatus::Authorized,
        ];
    }

    public function execute(
        ExpireVisitCommand $command
    ): VisitRecord {
        return $this->transactions->run(
            function () use (
                $command
            ): VisitRecord {
                $visit = VisitRecord::query()
                    ->lockForUpdate()
                    ->find($command->visitId);

                if (! $visit instanceof VisitRecord) {
                    throw VisitOperationException::visitNotFound();
                }

                if (
                    $visit->status === VisitStatus::Expired
                ) {
                    return $visit;
                }

                if (
                    ! in_array(
                        $visit->status,
                        self::eligibleStatuses(),
                        true
                    )
                    || $visit->checked_in_at !== null
                ) {
                    return $visit;
                }

                $deadline = $visit->expected_end_at
                    ?? $visit->expected_start_at;

                if ($deadline === null) {
                    return $visit;
                }

                $effectiveExpiredAt = CarbonImmutable::instance(
                    $deadline
                )->addHours(
                    max(
                        0,
                        $command->graceHours
                    )
                );

                $referenceAt = CarbonImmutable::instance(
                    $command->referenceAt
                );

                if (
                    $effectiveExpiredAt->isAfter(
                        $referenceAt
                    )
                ) {
                    return $visit;
                }

                $visit->fill([
                    'status' => VisitStatus::Expired,
                    'expired_at' => $effectiveExpiredAt,
                ]);

                $visit->save();

                return $visit;
            }
        );
    }
}
