<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialCredentials\Plan;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialPhotoDescriptor;

final readonly class PlanFacialCredentialSynchronizationCommand
{
    public function __construct(
        public ?string $deviceModel,
        public ?string $firmwareVersion,
        public IntelbrasFacialCredentialOperation $operation,
        public string $externalUserId,
        public ?string $displayName,
        public string $photoSha256,
        public int $photoSizeBytes,
        public int $photoWidth,
        public int $photoHeight,
        public string $photoMimeType =
            IntelbrasFacialPhotoDescriptor::MIME_TYPE,
    ) {}
}
