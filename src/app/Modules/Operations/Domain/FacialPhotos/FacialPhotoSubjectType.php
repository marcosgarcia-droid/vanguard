<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain\FacialPhotos;

enum FacialPhotoSubjectType: string
{
    case Visitor = 'visitor';
    case Employee = 'employee';
}
