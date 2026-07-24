<?php

namespace App\Modules\Operations\Domain\FacialPhotos;

enum FacialPhotoSource: string
{
    case Webcam = 'webcam';
    case FileUpload = 'file_upload';
    case FacialDevice = 'facial_device';
    case Import = 'import';

    public function label(): string
    {
        return match ($this) {
            self::Webcam => 'Webcam',
            self::FileUpload => 'Arquivo enviado',
            self::FacialDevice => 'Equipamento facial',
            self::Import => 'Importação',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
