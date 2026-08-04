<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Normalization;

use RuntimeException;
use Throwable;

final class FacialPhotoNormalizationException extends RuntimeException
{
    public function __construct(
        public readonly string $failureCode,
        string $message,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            previous: $previous
        );
    }

    public static function disabled(): self
    {
        return new self(
            'normalization_disabled',
            'A normalização da foto facial está desativada.'
        );
    }

    public static function sourceUnavailable(): self
    {
        return new self(
            'source_unavailable',
            'O arquivo original da foto facial não está disponível.'
        );
    }

    public static function sourceTooLarge(): self
    {
        return new self(
            'source_too_large',
            'O arquivo original da foto facial excede o limite permitido.'
        );
    }

    public static function unsupportedFormat(): self
    {
        return new self(
            'unsupported_format',
            'O formato do arquivo original não é compatível com a normalização.'
        );
    }

    public static function pixelLimitExceeded(): self
    {
        return new self(
            'pixel_limit_exceeded',
            'As dimensões do arquivo original excedem o limite de segurança.'
        );
    }

    public static function decodeFailed(
        ?Throwable $previous = null
    ): self {
        return new self(
            'decode_failed',
            'Não foi possível interpretar a imagem original.',
            $previous
        );
    }

    public static function temporaryDirectoryUnavailable(
        ?Throwable $previous = null
    ): self {
        return new self(
            'temporary_directory_unavailable',
            'O armazenamento temporário da normalização não está disponível.',
            $previous
        );
    }

    public static function outputWriteFailed(
        ?Throwable $previous = null
    ): self {
        return new self(
            'output_write_failed',
            'Não foi possível gravar a imagem facial normalizada.',
            $previous
        );
    }

    public static function invalidOutput(): self
    {
        return new self(
            'invalid_output',
            'A imagem facial normalizada não possui uma estrutura válida.'
        );
    }

    public static function outputTooLarge(): self
    {
        return new self(
            'output_too_large',
            'A imagem facial normalizada excede o limite permitido.'
        );
    }

    public static function processingFailed(
        ?Throwable $previous = null
    ): self {
        return new self(
            'processing_failed',
            'Não foi possível normalizar a foto facial.',
            $previous
        );
    }
}
