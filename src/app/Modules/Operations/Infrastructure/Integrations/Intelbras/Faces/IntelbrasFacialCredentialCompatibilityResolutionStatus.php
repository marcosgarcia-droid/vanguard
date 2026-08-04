<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

enum IntelbrasFacialCredentialCompatibilityResolutionStatus: string
{
    case MissingModel = 'missing_model';

    case InvalidModel = 'invalid_model';

    case UnknownModel = 'unknown_model';

    case MissingFirmware = 'missing_firmware';

    case InvalidFirmware = 'invalid_firmware';

    case UnverifiedCombination = 'unverified_combination';

    case Compatible = 'compatible';
}
