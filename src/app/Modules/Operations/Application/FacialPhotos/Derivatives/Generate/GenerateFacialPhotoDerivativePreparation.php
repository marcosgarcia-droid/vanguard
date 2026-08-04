<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate;

use InvalidArgumentException;

final readonly class GenerateFacialPhotoDerivativePreparation
{
    private function __construct(
        public string $derivativeId,
        public ?string $attemptId,
        public ?string $absoluteSourcePath,
        public string $sourceSha256,
        public ?GenerateFacialPhotoDerivativeResult $reusedResult,
    ) {
        if (
            preg_match(
                '/\A[0-9a-f-]{36}\z/',
                $this->derivativeId
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'O identificador da derivação é inválido.'
            );
        }

        if (
            preg_match(
                '/\A[a-f0-9]{64}\z/',
                $this->sourceSha256
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'A assinatura da origem é inválida.'
            );
        }

        if (
            $this->reusedResult === null
            && (
                $this->attemptId === null
                || $this->absoluteSourcePath === null
                || ! str_starts_with(
                    $this->absoluteSourcePath,
                    DIRECTORY_SEPARATOR
                )
            )
        ) {
            throw new InvalidArgumentException(
                'A preparação da derivação está incompleta.'
            );
        }
    }

    public static function forAttempt(
        string $derivativeId,
        string $attemptId,
        string $absoluteSourcePath,
        string $sourceSha256,
    ): self {
        return new self(
            derivativeId: $derivativeId,
            attemptId: $attemptId,
            absoluteSourcePath: $absoluteSourcePath,
            sourceSha256: $sourceSha256,
            reusedResult: null,
        );
    }

    public static function reused(
        string $derivativeId,
        string $sourceSha256,
        GenerateFacialPhotoDerivativeResult $result,
    ): self {
        return new self(
            derivativeId: $derivativeId,
            attemptId: null,
            absoluteSourcePath: null,
            sourceSha256: $sourceSha256,
            reusedResult: $result,
        );
    }

    public function wasReused(): bool
    {
        return $this->reusedResult !== null;
    }

    public function hasAttempt(): bool
    {
        return $this->attemptId !== null;
    }
}
