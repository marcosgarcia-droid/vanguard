<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

interface IntelbrasFacialCredentialCompatibilityCatalog
{
    public function resolve(
        ?string $model,
        ?string $firmware,
    ): IntelbrasFacialCredentialCompatibilityResolution;
}
