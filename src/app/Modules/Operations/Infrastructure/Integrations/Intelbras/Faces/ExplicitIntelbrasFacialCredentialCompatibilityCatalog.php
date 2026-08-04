<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use InvalidArgumentException;

final readonly class ExplicitIntelbrasFacialCredentialCompatibilityCatalog implements IntelbrasFacialCredentialCompatibilityCatalog
{
    /**
     * @var array<string, IntelbrasDeviceModel>
     */
    private array $knownModels;

    /**
     * @var array<string, IntelbrasFacialCredentialCompatibilityProfile>
     */
    private array $profiles;

    /**
     * @param  list<IntelbrasDeviceModel>  $knownModels
     * @param  list<IntelbrasFacialCredentialCompatibilityProfile>  $documentedProfiles
     */
    public function __construct(
        array $knownModels,
        array $documentedProfiles,
    ) {
        if (! array_is_list($knownModels) || $knownModels === []) {
            throw new InvalidArgumentException(
                'O catálogo deve possuir ao menos um modelo conhecido.'
            );
        }

        if (! array_is_list($documentedProfiles)) {
            throw new InvalidArgumentException(
                'A lista de perfis documentados é inválida.'
            );
        }

        $knownModelsByValue = [];

        foreach ($knownModels as $knownModel) {
            if (! $knownModel instanceof IntelbrasDeviceModel) {
                throw new InvalidArgumentException(
                    'O catálogo possui um modelo conhecido inválido.'
                );
            }

            if (isset($knownModelsByValue[$knownModel->value])) {
                throw new InvalidArgumentException(
                    'O catálogo possui um modelo conhecido duplicado.'
                );
            }

            $knownModelsByValue[$knownModel->value] = $knownModel;
        }

        $profilesByKey = [];

        foreach ($documentedProfiles as $profile) {
            if (
                ! $profile
                    instanceof IntelbrasFacialCredentialCompatibilityProfile
            ) {
                throw new InvalidArgumentException(
                    'O catálogo possui um perfil documentado inválido.'
                );
            }

            $profileModel = new IntelbrasDeviceModel(
                $profile->model
            );

            $profileFirmware = new IntelbrasFirmwareVersion(
                $profile->firmware
            );

            if (! isset($knownModelsByValue[$profileModel->value])) {
                throw new InvalidArgumentException(
                    'O perfil documentado referencia um modelo desconhecido.'
                );
            }

            $key = self::profileKey(
                model: $profileModel,
                firmware: $profileFirmware,
            );

            if (isset($profilesByKey[$key])) {
                throw new InvalidArgumentException(
                    'O catálogo possui um perfil documentado duplicado.'
                );
            }

            $profilesByKey[$key] = $profile;
        }

        $this->knownModels = $knownModelsByValue;
        $this->profiles = $profilesByKey;
    }

    public function resolve(
        ?string $model,
        ?string $firmware,
    ): IntelbrasFacialCredentialCompatibilityResolution {
        if ($model === null || trim($model) === '') {
            return IntelbrasFacialCredentialCompatibilityResolution::missingModel();
        }

        try {
            $normalizedModel = new IntelbrasDeviceModel(
                $model
            );
        } catch (InvalidArgumentException) {
            return IntelbrasFacialCredentialCompatibilityResolution::invalidModel();
        }

        if (! isset($this->knownModels[$normalizedModel->value])) {
            return IntelbrasFacialCredentialCompatibilityResolution::unknownModel(
                $normalizedModel
            );
        }

        if ($firmware === null || trim($firmware) === '') {
            return IntelbrasFacialCredentialCompatibilityResolution::missingFirmware(
                $normalizedModel
            );
        }

        try {
            $normalizedFirmware = new IntelbrasFirmwareVersion(
                $firmware
            );
        } catch (InvalidArgumentException) {
            return IntelbrasFacialCredentialCompatibilityResolution::invalidFirmware(
                $normalizedModel
            );
        }

        $profile = $this->profiles[
            self::profileKey(
                model: $normalizedModel,
                firmware: $normalizedFirmware,
            )
        ] ?? null;

        if ($profile === null) {
            return IntelbrasFacialCredentialCompatibilityResolution::unverifiedCombination(
                model: $normalizedModel,
                firmware: $normalizedFirmware,
            );
        }

        return IntelbrasFacialCredentialCompatibilityResolution::compatible(
            model: $normalizedModel,
            firmware: $normalizedFirmware,
            profile: $profile,
        );
    }

    private static function profileKey(
        IntelbrasDeviceModel $model,
        IntelbrasFirmwareVersion $firmware,
    ): string {
        return $model->value."\x1F".$firmware->value;
    }
}
