<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialCredentials\Create;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;

final readonly class CreateFacialCredentialSynchronizationCommand
{
    public function __construct(
        public string $visitorId,
        public string $accessDeviceId,
        public IntelbrasFacialCredentialOperation $operation,
    ) {}
}
