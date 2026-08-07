<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain\FacialCredentials;

enum FacialCredentialSubjectType: string
{
    case Visitor = 'visitor';

    case Employee = 'employee';
}
