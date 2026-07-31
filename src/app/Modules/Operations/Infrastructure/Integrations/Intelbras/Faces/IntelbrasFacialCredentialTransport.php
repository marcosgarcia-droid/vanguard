<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

enum IntelbrasFacialCredentialTransport: string
{
    case AccessFaceBatch = 'access_face_batch';

    case FaceInfoManagerSingle = 'face_info_manager_single';

    public function endpointPath(): string
    {
        return match ($this) {
            self::AccessFaceBatch => '/cgi-bin/AccessFace.cgi',
            self::FaceInfoManagerSingle => '/cgi-bin/FaceInfoManager.cgi',
        };
    }
}
