<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision;

use RuntimeException;
use Throwable;

final class LocalVisionFacialPhotoClientException extends RuntimeException
{
    public function __construct(
        public readonly LocalVisionFacialPhotoClientFailure $failure,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            previous: $previous,
        );
    }

    public static function invalidConfiguration(): self
    {
        return new self(
            LocalVisionFacialPhotoClientFailure::InvalidConfiguration,
            'A integração local de visão facial não está configurada corretamente.'
        );
    }

    public static function imageUnavailable(): self
    {
        return new self(
            LocalVisionFacialPhotoClientFailure::ImageUnavailable,
            'A imagem facial não está disponível para análise.'
        );
    }

    public static function serviceUnavailable(
        ?Throwable $previous = null
    ): self {
        return new self(
            LocalVisionFacialPhotoClientFailure::ServiceUnavailable,
            'O serviço local de visão facial está indisponível.',
            $previous,
        );
    }

    public static function requestRejected(
        int $status
    ): self {
        return new self(
            LocalVisionFacialPhotoClientFailure::RequestRejected,
            "O serviço local de visão facial rejeitou a requisição HTTP {$status}."
        );
    }

    public static function invalidResponse(
        ?Throwable $previous = null
    ): self {
        return new self(
            LocalVisionFacialPhotoClientFailure::InvalidResponse,
            'O serviço local de visão facial retornou uma resposta inválida.',
            $previous,
        );
    }
}
