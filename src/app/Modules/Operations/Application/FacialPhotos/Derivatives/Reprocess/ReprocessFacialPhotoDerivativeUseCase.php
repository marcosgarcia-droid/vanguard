<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess;

use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeCommand;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Schedule\FacialPhotoDerivativeAfterCommitScheduler;
use Throwable;

final readonly class ReprocessFacialPhotoDerivativeUseCase
{
    public function __construct(
        private ReprocessFacialPhotoDerivativeRepository $repository,
        private FacialPhotoDerivativeAfterCommitScheduler $scheduler,
    ) {}

    public function execute(
        ReprocessFacialPhotoDerivativeCommand $command
    ): ReprocessFacialPhotoDerivativeResult {
        if (! $this->isEnabled()) {
            throw ReprocessFacialPhotoDerivativeException::featureDisabled();
        }

        $profile = (string) config(
            'facial_photos.normalization.default_profile',
            'vanguard_normalized'
        );

        $policyVersion = (string) config(
            'facial_photos.normalization.policy_version',
            'vanguard-normalization-v1'
        );

        $context = $this->repository->prepare(
            command: $command,
            profile: $profile,
            policyVersion: $policyVersion,
        );

        try {
            $scheduled = $this->scheduler->schedule(
                new GenerateFacialPhotoDerivativeCommand(
                    photoId: $context->photoId,
                    profile: $profile,
                    policyVersion: $policyVersion,
                    normalizer: (string) config(
                        'facial_photos.normalization.normalizer',
                        'spatie-gd'
                    ),
                    normalizerVersion: (string) config(
                        'facial_photos.normalization.normalizer_version',
                        'spatie-gd-v1'
                    ),
                    requestedBy: $command->operatorUserId,
                    requesterName: $context->requesterName,
                )
            );
        } catch (Throwable $throwable) {
            throw ReprocessFacialPhotoDerivativeException::schedulingFailed(
                $throwable
            );
        }

        if (! $scheduled) {
            throw ReprocessFacialPhotoDerivativeException::schedulingFailed();
        }

        return new ReprocessFacialPhotoDerivativeResult(
            requestId: $command->requestId,
            photoId: $context->photoId,
            previousStatus: $context->previousStatus,
            scheduled: true,
        );
    }

    private function isEnabled(): bool
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
}
