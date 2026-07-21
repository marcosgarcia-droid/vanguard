<?php

namespace App\Modules\Operations\Application\Visits\VehicleAuthorization;

use RuntimeException;

final class VisitVehicleAuthorizationException extends RuntimeException
{
    public static function vehicleNotFound(): self
    {
        return new self(
            'O veículo informado não foi encontrado.'
        );
    }

    public static function requestNotFound(): self
    {
        return new self(
            'A solicitação de autorização do veículo não foi encontrada.'
        );
    }

    public static function userUnavailable(): self
    {
        return new self(
            'O usuário responsável pela operação não está disponível.'
        );
    }

    public static function pendingRequestAlreadyExists(): self
    {
        return new self(
            'Já existe uma solicitação de autorização pendente para este veículo.'
        );
    }

    public static function requestAlreadyDecided(): self
    {
        return new self(
            'A solicitação de autorização do veículo já foi decidida.'
        );
    }

    public static function vehicleAuthorizationAlreadyDecided(): self
    {
        return new self(
            'A autorização de entrada deste veículo já foi decidida.'
        );
    }

    public static function invalidDecision(): self
    {
        return new self(
            'A decisão deve autorizar ou recusar a entrada do veículo.'
        );
    }

    public static function rejectionReasonRequired(): self
    {
        return new self(
            'Informe o motivo da recusa da entrada do veículo.'
        );
    }

    public static function authorizationDenied(): self
    {
        return new self(
            'O usuário não possui permissão para decidir a entrada do veículo.'
        );
    }

    public static function contextMismatch(): self
    {
        return new self(
            'O veículo ou a solicitação não pertence ao contexto empresarial informado.'
        );
    }
}
