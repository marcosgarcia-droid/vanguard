<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Queue;

use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeCommand;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Schedule\FacialPhotoDerivativeAfterCommitScheduler;

final readonly class AdditionalFacialPhotoDerivativeAfterCommitScheduler implements FacialPhotoDerivativeAfterCommitScheduler
{
    public function __construct(
        private FacialPhotoDerivativeAfterCommitScheduler $scheduler,
        private string $additionalProfile,
        private string $additionalPolicyVersion,
        private string $additionalNormalizer,
        private string $additionalNormalizerVersion,
    ) {}

    public function schedule(
        GenerateFacialPhotoDerivativeCommand $command
    ): bool {
        if (! $this->scheduler->schedule($command)) {
            return false;
        }

        if ($this->matchesAdditionalSpecification($command)) {
            return true;
        }

        return $this->scheduler->schedule(
            new GenerateFacialPhotoDerivativeCommand(
                photoId: $command->photoId,
                profile: $this->additionalProfile,
                policyVersion: $this->additionalPolicyVersion,
                normalizer: $this->additionalNormalizer,
                normalizerVersion: $this->additionalNormalizerVersion,
                requestedBy: $command->requestedBy,
                requesterName: $command->requesterName,
            )
        );
    }

    private function matchesAdditionalSpecification(
        GenerateFacialPhotoDerivativeCommand $command
    ): bool {
        return hash_equals(
            $this->additionalProfile,
            $command->profile->value
        )
            && hash_equals(
                $this->additionalPolicyVersion,
                $command->policyVersion
            )
            && hash_equals(
                $this->additionalNormalizer,
                $command->normalizer
            )
            && hash_equals(
                $this->additionalNormalizerVersion,
                $command->normalizerVersion
            );
    }
}
