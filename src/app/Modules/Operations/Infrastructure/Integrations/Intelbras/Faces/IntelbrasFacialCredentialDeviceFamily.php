<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

enum IntelbrasFacialCredentialDeviceFamily: string
{
    case BatchCapable = 'batch_capable';

    case SinglePerson = 'single_person';
}
