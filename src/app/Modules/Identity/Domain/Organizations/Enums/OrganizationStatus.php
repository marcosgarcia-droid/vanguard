<?php

namespace App\Modules\Identity\Domain\Organizations\Enums;

enum OrganizationStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
