<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

interface IntelbrasFacialCredentialSynchronizerResolver
{
    public function resolve(): IntelbrasFacialCredentialSynchronizer;
}
