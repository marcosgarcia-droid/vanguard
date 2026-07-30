<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision;

enum LocalVisionFacialPhotoClientFailure: string
{
    case InvalidConfiguration = 'invalid_configuration';

    case ImageUnavailable = 'image_unavailable';

    case ServiceUnavailable = 'service_unavailable';

    case RequestRejected = 'request_rejected';

    case InvalidResponse = 'invalid_response';
}
