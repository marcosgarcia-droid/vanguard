<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeCommand;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeContext;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeException;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeRepository;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSubjectType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class EloquentReprocessFacialPhotoDerivativeRepository implements ReprocessFacialPhotoDerivativeRepository
{
    public function prepare(
        ReprocessFacialPhotoDerivativeCommand $command,
        string $profile,
        string $policyVersion,
    ): ReprocessFacialPhotoDerivativeContext {
        return DB::transaction(
            function () use (
                $command,
                $profile,
                $policyVersion
            ): ReprocessFacialPhotoDerivativeContext {
                $subject = $this->subject(
                    $command
                );

                $operator = User::query()
                    ->whereKey($command->operatorUserId)
                    ->first();

                if (! $operator instanceof User) {
                    throw ReprocessFacialPhotoDerivativeException::operatorNotFound();
                }

                if (
                    ! Gate::forUser($operator)->allows(
                        'reprocessFacialPhotoDerivative',
                        $subject
                    )
                ) {
                    throw ReprocessFacialPhotoDerivativeException::unauthorized();
                }

                $photo = $this->latestFacialPhoto(
                    $subject
                );

                if (! $photo instanceof FacialPhotoRecord) {
                    throw ReprocessFacialPhotoDerivativeException::photoNotFound(
                        $command->subjectType
                    );
                }

                if (
                    (string) $photo->tenant_id
                        !== (string) $subject->tenant_id
                    || (string) $photo->organization_id
                        !== (string) $subject->organization_id
                ) {
                    throw ReprocessFacialPhotoDerivativeException::unauthorized();
                }

                if (! $photo->isApproved()) {
                    throw ReprocessFacialPhotoDerivativeException::photoNotApproved();
                }

                if (
                    ! is_string($photo->sha256)
                    || preg_match(
                        '/\A[a-f0-9]{64}\z/',
                        $photo->sha256
                    ) !== 1
                ) {
                    throw ReprocessFacialPhotoDerivativeException::sourceUnavailable();
                }

                $derivative =
                    FacialPhotoDerivativeRecord::query()
                        ->where(
                            'facial_photo_id',
                            $photo->getKey()
                        )
                        ->where(
                            'profile',
                            $profile
                        )
                        ->where(
                            'policy_version',
                            $policyVersion
                        )
                        ->where(
                            'source_sha256',
                            $photo->sha256
                        )
                        ->orderByDesc('created_at')
                        ->orderByDesc('id')
                        ->lockForUpdate()
                        ->first();

                $status = $this->statusOf(
                    $derivative
                );

                if (
                    $status
                        === FacialPhotoDerivativeStatus::Processing
                ) {
                    throw ReprocessFacialPhotoDerivativeException::alreadyProcessing();
                }

                if (
                    $status
                        === FacialPhotoDerivativeStatus::Ready
                ) {
                    throw ReprocessFacialPhotoDerivativeException::alreadyReady();
                }

                if (
                    $status
                        === FacialPhotoDerivativeStatus::Superseded
                ) {
                    throw ReprocessFacialPhotoDerivativeException::staleDerivative();
                }

                return new ReprocessFacialPhotoDerivativeContext(
                    photoId: (string) $photo->getKey(),
                    requesterName: (string) $operator->name,
                    previousStatus: $status,
                );
            }
        );
    }

    private function subject(
        ReprocessFacialPhotoDerivativeCommand $command
    ): VisitorRecord|EmployeeRecord {
        $subject = match ($command->subjectType) {
            FacialPhotoSubjectType::Visitor => VisitorRecord::query()
                ->whereKey($command->subjectId)
                ->lockForUpdate()
                ->first(),

            FacialPhotoSubjectType::Employee => EmployeeRecord::query()
                ->whereKey($command->subjectId)
                ->lockForUpdate()
                ->first(),
        };

        if (
            $command->subjectType === FacialPhotoSubjectType::Visitor
            && $subject instanceof VisitorRecord
        ) {
            return $subject;
        }

        if (
            $command->subjectType === FacialPhotoSubjectType::Employee
            && $subject instanceof EmployeeRecord
        ) {
            return $subject;
        }

        throw ReprocessFacialPhotoDerivativeException::subjectNotFound(
            $command->subjectType
        );
    }

    private function latestFacialPhoto(
        VisitorRecord|EmployeeRecord $subject
    ): ?FacialPhotoRecord {
        if ($subject instanceof VisitorRecord) {
            $photo = $subject
                ->latestFacialPhoto()
                ->lockForUpdate()
                ->first();

            return $photo instanceof FacialPhotoRecord
                ? $photo
                : null;
        }

        $photo = $subject
            ->facialPhotos()
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        return $photo instanceof FacialPhotoRecord
            ? $photo
            : null;
    }

    private function statusOf(
        ?FacialPhotoDerivativeRecord $derivative
    ): ?FacialPhotoDerivativeStatus {
        if (
            ! $derivative instanceof FacialPhotoDerivativeRecord
        ) {
            return null;
        }

        return $derivative->status
            instanceof FacialPhotoDerivativeStatus
                ? $derivative->status
                : FacialPhotoDerivativeStatus::tryFrom(
                    (string) $derivative->status
                );
    }
}
