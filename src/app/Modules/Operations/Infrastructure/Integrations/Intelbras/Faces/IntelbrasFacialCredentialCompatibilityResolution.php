<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use LogicException;

final readonly class IntelbrasFacialCredentialCompatibilityResolution
{
    private function __construct(
        public IntelbrasFacialCredentialCompatibilityResolutionStatus $status,
        public ?IntelbrasDeviceModel $model,
        public ?IntelbrasFirmwareVersion $firmware,
        public ?IntelbrasFacialCredentialCompatibilityProfile $profile,
    ) {
        if (
            $status
                === IntelbrasFacialCredentialCompatibilityResolutionStatus::Compatible
        ) {
            if (
                $model === null
                || $firmware === null
                || $profile === null
            ) {
                throw new LogicException(
                    'Uma resolução compatível exige modelo, firmware e perfil.'
                );
            }

            $profileModel = new IntelbrasDeviceModel(
                $profile->model
            );

            $profileFirmware = new IntelbrasFirmwareVersion(
                $profile->firmware
            );

            if (
                ! $model->equals($profileModel)
                || ! $firmware->equals($profileFirmware)
            ) {
                throw new LogicException(
                    'O perfil não corresponde ao modelo e firmware resolvidos.'
                );
            }

            return;
        }

        if ($profile !== null) {
            throw new LogicException(
                'Uma resolução incompatível não pode expor um perfil.'
            );
        }
    }

    public static function missingModel(): self
    {
        return new self(
            status: IntelbrasFacialCredentialCompatibilityResolutionStatus::MissingModel,
            model: null,
            firmware: null,
            profile: null,
        );
    }

    public static function invalidModel(): self
    {
        return new self(
            status: IntelbrasFacialCredentialCompatibilityResolutionStatus::InvalidModel,
            model: null,
            firmware: null,
            profile: null,
        );
    }

    public static function unknownModel(
        IntelbrasDeviceModel $model,
    ): self {
        return new self(
            status: IntelbrasFacialCredentialCompatibilityResolutionStatus::UnknownModel,
            model: $model,
            firmware: null,
            profile: null,
        );
    }

    public static function missingFirmware(
        IntelbrasDeviceModel $model,
    ): self {
        return new self(
            status: IntelbrasFacialCredentialCompatibilityResolutionStatus::MissingFirmware,
            model: $model,
            firmware: null,
            profile: null,
        );
    }

    public static function invalidFirmware(
        IntelbrasDeviceModel $model,
    ): self {
        return new self(
            status: IntelbrasFacialCredentialCompatibilityResolutionStatus::InvalidFirmware,
            model: $model,
            firmware: null,
            profile: null,
        );
    }

    public static function unverifiedCombination(
        IntelbrasDeviceModel $model,
        IntelbrasFirmwareVersion $firmware,
    ): self {
        return new self(
            status: IntelbrasFacialCredentialCompatibilityResolutionStatus::UnverifiedCombination,
            model: $model,
            firmware: $firmware,
            profile: null,
        );
    }

    public static function compatible(
        IntelbrasDeviceModel $model,
        IntelbrasFirmwareVersion $firmware,
        IntelbrasFacialCredentialCompatibilityProfile $profile,
    ): self {
        return new self(
            status: IntelbrasFacialCredentialCompatibilityResolutionStatus::Compatible,
            model: $model,
            firmware: $firmware,
            profile: $profile,
        );
    }

    public function isCompatible(): bool
    {
        return $this->status
            === IntelbrasFacialCredentialCompatibilityResolutionStatus::Compatible;
    }

    public function supportsOperation(
        IntelbrasFacialCredentialOperation $operation,
    ): bool {
        if (! $this->isCompatible() || $this->profile === null) {
            return false;
        }

        return match ($operation) {
            IntelbrasFacialCredentialOperation::Register => true,
            IntelbrasFacialCredentialOperation::Replace => $this->profile->supportsReplacement,
        };
    }

    /**
     * @return array{
     *     status: string,
     *     model: string|null,
     *     firmware: string|null,
     *     compatible: bool,
     *     supports_register: bool,
     *     supports_replace: bool,
     *     profile: array<string, bool|int|string>|null
     * }
     */
    public function toSafeArray(): array
    {
        return [
            'status' => $this->status->value,
            'model' => $this->model?->value,
            'firmware' => $this->firmware?->value,
            'compatible' => $this->isCompatible(),
            'supports_register' => $this->supportsOperation(
                IntelbrasFacialCredentialOperation::Register
            ),
            'supports_replace' => $this->supportsOperation(
                IntelbrasFacialCredentialOperation::Replace
            ),
            'profile' => $this->profile?->toSafeArray(),
        ];
    }
}
