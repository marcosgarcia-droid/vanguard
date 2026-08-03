<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

enum IntelbrasFacialCredentialOperation: string
{
    case Register = 'register';

    case Replace = 'replace';
}
