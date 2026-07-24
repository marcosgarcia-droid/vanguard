<?php

namespace App\Modules\Operations\Infrastructure\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

final class VisitorPhotoUploadStorage
{
    public function store(UploadedFile $file): string
    {
        Validator::make(
            [
                'photo' => $file,
            ],
            [
                'photo' => [
                    'required',
                    'file',
                    'image',
                    'mimetypes:image/jpeg,image/png,image/webp',
                    'max:5120',
                ],
            ],
            [
                'photo.required' => 'Capture ou selecione uma foto.',
                'photo.file' => 'O arquivo da foto não é válido.',
                'photo.image' => 'Selecione uma imagem válida.',
                'photo.mimetypes' => 'A foto deve estar em JPG, PNG ou WebP.',
                'photo.max' => 'A foto deve possuir no máximo 5 MB.',
            ],
        )->validate();

        $path = $file->store(
            'visitors/photos',
            'local'
        );

        if (! is_string($path) || blank($path)) {
            throw new RuntimeException(
                'Não foi possível armazenar a foto do visitante.'
            );
        }

        return $path;
    }
}
