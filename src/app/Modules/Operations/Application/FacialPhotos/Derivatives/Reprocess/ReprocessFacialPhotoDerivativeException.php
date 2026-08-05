<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess;

use RuntimeException;
use Throwable;

final class ReprocessFacialPhotoDerivativeException extends RuntimeException
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

    public static function featureDisabled(): self
    {
        return new self(
            'facial_derivative_generation_disabled',
            'A preparação automática da foto facial está desativada.'
        );
    }

    public static function visitorNotFound(): self
    {
        return new self(
            'visitor_not_found',
            'O visitante não foi localizado.'
        );
    }

    public static function operatorNotFound(): self
    {
        return new self(
            'operator_not_found',
            'O operador responsável não foi localizado.'
        );
    }

    public static function unauthorized(): self
    {
        return new self(
            'operation_not_authorized',
            'Você não possui autorização para reprocessar esta foto facial.'
        );
    }

    public static function photoNotFound(): self
    {
        return new self(
            'facial_photo_not_found',
            'O visitante não possui uma foto facial cadastrada.'
        );
    }

    public static function photoNotApproved(): self
    {
        return new self(
            'facial_photo_not_approved',
            'Somente uma foto facial aprovada pode ser reprocessada.'
        );
    }

    public static function sourceUnavailable(): self
    {
        return new self(
            'facial_photo_source_unavailable',
            'A origem da foto facial não está disponível. Atualize a foto antes de tentar novamente.'
        );
    }

    public static function alreadyProcessing(): self
    {
        return new self(
            'facial_derivative_already_processing',
            'A preparação desta foto facial já está em andamento.'
        );
    }

    public static function alreadyReady(): self
    {
        return new self(
            'facial_derivative_already_ready',
            'A foto facial já possui uma preparação válida.'
        );
    }

    public static function staleDerivative(): self
    {
        return new self(
            'facial_derivative_stale',
            'Esta preparação foi substituída por uma versão mais recente.'
        );
    }

    public static function schedulingFailed(
        ?Throwable $previous = null
    ): self {
        return new self(
            'facial_derivative_scheduling_failed',
            'Não foi possível solicitar o reprocessamento da foto facial.',
            $previous
        );
    }
}
