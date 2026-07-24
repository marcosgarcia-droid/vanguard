<?php

namespace App\Modules\Operations\Infrastructure\Storage;

use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use LogicException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

final class FacialPhotoPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->basePath($media).'original/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->basePath($media).'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->basePath($media).'responsive-images/';
    }

    private function basePath(Media $media): string
    {
        $photo = $media->model;

        if (! $photo instanceof FacialPhotoRecord) {
            throw new LogicException(
                'O gerador de caminho facial recebeu um model incompatível.'
            );
        }

        $mediaReference = filled($media->uuid)
            ? (string) $media->uuid
            : (string) $media->getKey();

        return sprintf(
            'tenants/%s/organizations/%s/photos/%s/media/%s/',
            $photo->tenant_id,
            $photo->organization_id,
            $photo->getKey(),
            $mediaReference,
        );
    }
}
