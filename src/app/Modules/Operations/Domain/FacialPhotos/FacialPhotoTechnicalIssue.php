<?php

namespace App\Modules\Operations\Domain\FacialPhotos;

enum FacialPhotoTechnicalIssue: string
{
    case FileUnavailable = 'file_unavailable';
    case UnsupportedFormat = 'unsupported_format';
    case OriginalFileTooLarge = 'original_file_too_large';
    case PixelLimitExceeded = 'pixel_limit_exceeded';
    case ResolutionTooLow = 'resolution_too_low';
    case AspectRatioInvalid = 'aspect_ratio_invalid';
    case DecodeFailed = 'decode_failed';
    case Underexposed = 'underexposed';
    case Overexposed = 'overexposed';
    case LowContrast = 'low_contrast';
    case LowSharpness = 'low_sharpness';

    public function label(): string
    {
        return match ($this) {
            self::FileUnavailable => 'Arquivo indisponível',
            self::UnsupportedFormat => 'Formato não suportado',
            self::OriginalFileTooLarge => 'Arquivo muito grande',
            self::PixelLimitExceeded => 'Imagem com dimensões excessivas',
            self::ResolutionTooLow => 'Resolução insuficiente',
            self::AspectRatioInvalid => 'Proporção inadequada',
            self::DecodeFailed => 'Imagem inválida ou corrompida',
            self::Underexposed => 'Imagem muito escura',
            self::Overexposed => 'Imagem muito clara',
            self::LowContrast => 'Contraste insuficiente',
            self::LowSharpness => 'Imagem sem nitidez suficiente',
        };
    }

    public function guidance(): string
    {
        return match ($this) {
            self::FileUnavailable => 'Selecione ou capture novamente a foto.',
            self::UnsupportedFormat => 'Utilize uma imagem JPG, PNG ou WebP.',
            self::OriginalFileTooLarge => 'Utilize uma imagem com no máximo 5 MB.',
            self::PixelLimitExceeded => 'Reduza as dimensões da imagem e tente novamente.',
            self::ResolutionTooLow => 'Utilize uma imagem com pelo menos 500 × 500 pixels.',
            self::AspectRatioInvalid => 'Utilize uma foto vertical, semelhante ao padrão 3×4.',
            self::DecodeFailed => 'O arquivo não pôde ser lido como uma imagem válida.',
            self::Underexposed => 'Aumente a iluminação frontal e evite ambientes escuros.',
            self::Overexposed => 'Reduza a luz direta e evite reflexos sobre o rosto.',
            self::LowContrast => 'Melhore a iluminação e evite fundo e rosto com tons semelhantes.',
            self::LowSharpness => 'Mantenha a câmera firme e aguarde o foco antes de capturar.',
        };
    }
}
