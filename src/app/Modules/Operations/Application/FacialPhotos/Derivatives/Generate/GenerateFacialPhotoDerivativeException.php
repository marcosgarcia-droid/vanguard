<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate;

use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationException;
use RuntimeException;
use Throwable;

final class GenerateFacialPhotoDerivativeException extends RuntimeException
{
    public function __construct(
        public readonly string $failureCode,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message,
            previous: $previous
        );
    }

    public static function generationInProgress(): self
    {
        return new self(
            'generation_in_progress',
            'A preparação desta foto facial já está em andamento.'
        );
    }

    public static function photoNotFound(): self
    {
        return new self(
            'photo_not_found',
            'A foto facial solicitada não foi localizada.'
        );
    }

    public static function photoNotApproved(): self
    {
        return new self(
            'photo_not_approved',
            'Somente uma foto facial aprovada pode ser preparada.'
        );
    }

    public static function sourceUnavailable(): self
    {
        return new self(
            'source_unavailable',
            'O arquivo original da foto facial não está disponível.'
        );
    }

    public static function sourceChanged(): self
    {
        return new self(
            'source_changed',
            'O arquivo original da foto facial foi alterado.'
        );
    }

    public static function attemptLimitReached(): self
    {
        return new self(
            'attempt_limit_reached',
            'O limite de tentativas desta preparação foi atingido.'
        );
    }

    public static function invalidNormalizerOutput(): self
    {
        return new self(
            'invalid_normalizer_output',
            'O resultado da normalização não corresponde à solicitação.'
        );
    }

    public static function persistedArtifactMismatch(): self
    {
        return new self(
            'persisted_artifact_mismatch',
            'O artefato persistido não corresponde ao arquivo normalizado.'
        );
    }

    public static function normalizationFailed(
        FacialPhotoNormalizationException $exception
    ): self {
        return new self(
            $exception->failureCode,
            'Não foi possível preparar a foto facial.',
            $exception
        );
    }

    public static function persistenceFailed(
        ?Throwable $previous = null
    ): self {
        return new self(
            'persistence_failed',
            'Não foi possível registrar a preparação da foto facial.',
            $previous
        );
    }
}
