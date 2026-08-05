<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialCredentials\Create;

use InvalidArgumentException;

final readonly class FacialCredentialSynchronizationContext
{
    public function __construct(
        public string $tenantId,
        public string $organizationId,
        public string $visitorId,
        public string $visitorDisplayName,
        public string $facialPhotoId,
        public string $facialPhotoDerivativeId,
        public string $accessDeviceId,
        public string $configurationSnapshotId,
        public string $deviceModel,
        public string $firmwareVersion,
        public string $derivativeSha256,
        public int $derivativeSizeBytes,
        public int $derivativeWidth,
        public int $derivativeHeight,
        public string $derivativeMimeType,
    ) {
        foreach (
            [
                'tenantId' => $tenantId,
                'organizationId' => $organizationId,
                'visitorId' => $visitorId,
                'facialPhotoId' => $facialPhotoId,
                'facialPhotoDerivativeId' => $facialPhotoDerivativeId,
                'accessDeviceId' => $accessDeviceId,
                'configurationSnapshotId' => $configurationSnapshotId,
                'deviceModel' => $deviceModel,
                'firmwareVersion' => $firmwareVersion,
                'derivativeMimeType' => $derivativeMimeType,
            ] as $field => $value
        ) {
            if (trim($value) === '') {
                throw new InvalidArgumentException(
                    sprintf(
                        'O campo %s do contexto facial é obrigatório.',
                        $field
                    )
                );
            }
        }

        if (
            preg_match(
                '/^[a-f0-9]{64}$/D',
                $derivativeSha256
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'O hash do derivado facial é inválido.'
            );
        }

        if (
            $derivativeSizeBytes <= 0
            || $derivativeWidth <= 0
            || $derivativeHeight <= 0
        ) {
            throw new InvalidArgumentException(
                'As dimensões do derivado facial são inválidas.'
            );
        }
    }
}
