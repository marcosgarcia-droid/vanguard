<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialCredentials\Plan;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityResolutionStatus;

enum FacialCredentialSynchronizationPlanningReason: string
{
    case Ready = 'ready';

    case MissingModel = 'missing_model';
    case InvalidModel = 'invalid_model';
    case UnknownModel = 'unknown_model';

    case MissingFirmware = 'missing_firmware';
    case InvalidFirmware = 'invalid_firmware';
    case UnverifiedCombination = 'unverified_combination';

    case UnsupportedOperation = 'unsupported_operation';
    case InvalidCredentialInput = 'invalid_credential_input';

    public static function fromCompatibilityStatus(
        IntelbrasFacialCredentialCompatibilityResolutionStatus $status
    ): self {
        return match ($status) {
            IntelbrasFacialCredentialCompatibilityResolutionStatus::MissingModel => self::MissingModel,

            IntelbrasFacialCredentialCompatibilityResolutionStatus::InvalidModel => self::InvalidModel,

            IntelbrasFacialCredentialCompatibilityResolutionStatus::UnknownModel => self::UnknownModel,

            IntelbrasFacialCredentialCompatibilityResolutionStatus::MissingFirmware => self::MissingFirmware,

            IntelbrasFacialCredentialCompatibilityResolutionStatus::InvalidFirmware => self::InvalidFirmware,

            IntelbrasFacialCredentialCompatibilityResolutionStatus::UnverifiedCombination => self::UnverifiedCombination,

            IntelbrasFacialCredentialCompatibilityResolutionStatus::Compatible => self::Ready,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Plano pronto',

            self::MissingModel => 'Modelo não informado',

            self::InvalidModel => 'Modelo inválido',

            self::UnknownModel => 'Modelo não reconhecido',

            self::MissingFirmware => 'Firmware não informado',

            self::InvalidFirmware => 'Firmware inválido',

            self::UnverifiedCombination => 'Compatibilidade ainda não comprovada',

            self::UnsupportedOperation => 'Operação não suportada',

            self::InvalidCredentialInput => 'Dados da credencial inválidos',
        };
    }

    public function isReady(): bool
    {
        return $this === self::Ready;
    }
}
