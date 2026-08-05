<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialCredentials\Create;

use App\Modules\Operations\Application\FacialCredentials\Plan\PlanFacialCredentialSynchronizationCommand;
use App\Modules\Operations\Application\FacialCredentials\Plan\PlanFacialCredentialSynchronizationUseCase;
use JsonException;
use LogicException;

final readonly class CreateFacialCredentialSynchronizationUseCase
{
    public function __construct(
        private CreateFacialCredentialSynchronizationRepository $repository,
        private PlanFacialCredentialSynchronizationUseCase $planner,
    ) {}

    public function execute(
        CreateFacialCredentialSynchronizationCommand $command
    ): CreateFacialCredentialSynchronizationResult {
        $preparation = $this->repository->prepare(
            $command
        );

        if (! $preparation->isReady()) {
            return CreateFacialCredentialSynchronizationResult::blocked(
                $preparation->reason
                    ?? CreateFacialCredentialSynchronizationReason::ContextChanged
            );
        }

        $context = $preparation->context;

        if (
            ! $context instanceof FacialCredentialSynchronizationContext
        ) {
            return CreateFacialCredentialSynchronizationResult::blocked(
                CreateFacialCredentialSynchronizationReason::ContextChanged
            );
        }

        $planning = $this->planner->execute(
            new PlanFacialCredentialSynchronizationCommand(
                deviceModel: $context->deviceModel,
                firmwareVersion: $context->firmwareVersion,
                operation: $command->operation,
                externalUserId: $context->visitorId,
                displayName: $context->visitorDisplayName,
                photoSha256: $context->derivativeSha256,
                photoSizeBytes: $context->derivativeSizeBytes,
                photoWidth: $context->derivativeWidth,
                photoHeight: $context->derivativeHeight,
                photoMimeType: $context->derivativeMimeType,
            )
        );

        if (! $planning->isReady()) {
            return CreateFacialCredentialSynchronizationResult::blocked(
                reason: CreateFacialCredentialSynchronizationReason::PlanningBlocked,
                planningReason: $planning->reason,
            );
        }

        $planFingerprint = $planning->planFingerprint();

        if (
            ! is_string($planFingerprint)
            || preg_match(
                '/^[a-f0-9]{64}$/D',
                $planFingerprint
            ) !== 1
        ) {
            return CreateFacialCredentialSynchronizationResult::blocked(
                CreateFacialCredentialSynchronizationReason::PlanningBlocked
            );
        }

        return $this->repository->persist(
            context: $context,
            operation: $command->operation,
            planFingerprint: $planFingerprint,
            contextFingerprint: $this->contextFingerprint(
                $context,
                $command,
                $planFingerprint
            ),
        );
    }

    private function contextFingerprint(
        FacialCredentialSynchronizationContext $context,
        CreateFacialCredentialSynchronizationCommand $command,
        string $planFingerprint,
    ): string {
        try {
            $serialized = json_encode(
                [
                    'version' => 1,
                    'tenant_id' => $context->tenantId,
                    'organization_id' => $context->organizationId,
                    'visitor_id' => $context->visitorId,
                    'facial_photo_id' => $context->facialPhotoId,
                    'facial_photo_derivative_id' => $context->facialPhotoDerivativeId,
                    'access_device_id' => $context->accessDeviceId,
                    'operation' => $command->operation->value,
                    'plan_fingerprint' => $planFingerprint,
                ],
                JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException $exception) {
            throw new LogicException(
                'Não foi possível serializar o contexto facial.',
                previous: $exception
            );
        }

        return hash(
            'sha256',
            $serialized
        );
    }
}
