<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialCredentials\Plan;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityCatalog;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialItem;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialPlan;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialPhotoDescriptor;
use InvalidArgumentException;

final readonly class PlanFacialCredentialSynchronizationUseCase
{
    public function __construct(
        private IntelbrasFacialCredentialCompatibilityCatalog $catalog
    ) {}

    public function execute(
        PlanFacialCredentialSynchronizationCommand $command
    ): PlanFacialCredentialSynchronizationResult {
        $compatibility = $this->catalog->resolve(
            model: $command->deviceModel,
            firmware: $command->firmwareVersion,
        );

        if (! $compatibility->isCompatible()) {
            return PlanFacialCredentialSynchronizationResult::blocked(
                reason: FacialCredentialSynchronizationPlanningReason::fromCompatibilityStatus(
                    $compatibility->status
                ),
                compatibility: $compatibility,
                operation: $command->operation,
            );
        }

        if (
            ! $compatibility->supportsOperation(
                $command->operation
            )
        ) {
            return PlanFacialCredentialSynchronizationResult::blocked(
                reason: FacialCredentialSynchronizationPlanningReason::UnsupportedOperation,
                compatibility: $compatibility,
                operation: $command->operation,
            );
        }

        try {
            $photo = new IntelbrasFacialPhotoDescriptor(
                sha256: $command->photoSha256,
                byteLength: $command->photoSizeBytes,
                width: $command->photoWidth,
                height: $command->photoHeight,
                mimeType: $command->photoMimeType,
            );

            $item = new IntelbrasFacialCredentialItem(
                externalUserId: $command->externalUserId,
                photo: $photo,
                displayName: $command->displayName,
            );

            $plan = new IntelbrasFacialCredentialPlan(
                compatibility: $compatibility->profile,
                operation: $command->operation,
                items: [$item],
            );
        } catch (InvalidArgumentException) {
            return PlanFacialCredentialSynchronizationResult::blocked(
                reason: FacialCredentialSynchronizationPlanningReason::InvalidCredentialInput,
                compatibility: $compatibility,
                operation: $command->operation,
            );
        }

        return PlanFacialCredentialSynchronizationResult::ready(
            compatibility: $compatibility,
            plan: $plan,
        );
    }
}
