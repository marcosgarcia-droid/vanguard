<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

final readonly class DocumentedIntelbrasFacialCredentialCompatibilityCatalog implements IntelbrasFacialCredentialCompatibilityCatalog
{
    public const SS_3532_MF = 'SS 3532 MF';

    private ExplicitIntelbrasFacialCredentialCompatibilityCatalog $catalog;

    public function __construct()
    {
        /*
         * O modelo é reconhecido como candidato de inventário.
         *
         * Nenhum perfil é liberado enquanto modelo, firmware, família,
         * operações e limites não forem comprovados conjuntamente.
         */
        $this->catalog =
            new ExplicitIntelbrasFacialCredentialCompatibilityCatalog(
                knownModels: [
                    new IntelbrasDeviceModel(
                        self::SS_3532_MF
                    ),
                ],
                documentedProfiles: [],
            );
    }

    public function resolve(
        ?string $model,
        ?string $firmware,
    ): IntelbrasFacialCredentialCompatibilityResolution {
        return $this->catalog->resolve(
            model: $model,
            firmware: $firmware,
        );
    }
}
