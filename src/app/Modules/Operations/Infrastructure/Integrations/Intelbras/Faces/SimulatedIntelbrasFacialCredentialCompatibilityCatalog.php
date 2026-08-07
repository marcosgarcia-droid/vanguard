<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

final readonly class SimulatedIntelbrasFacialCredentialCompatibilityCatalog implements IntelbrasFacialCredentialCompatibilityCatalog
{
    public const MODEL = 'VANGUARD FACIAL SIMULATOR';

    public const FIRMWARE = '20991231';

    private ExplicitIntelbrasFacialCredentialCompatibilityCatalog $catalog;

    public function __construct()
    {
        $this->catalog =
            new ExplicitIntelbrasFacialCredentialCompatibilityCatalog(
                knownModels: [
                    new IntelbrasDeviceModel(
                        self::MODEL
                    ),
                ],

                documentedProfiles: [
                    new IntelbrasFacialCredentialCompatibilityProfile(
                        family: IntelbrasFacialCredentialDeviceFamily::BatchCapable,

                        model: self::MODEL,
                        firmware: self::FIRMWARE,
                        maxItems: 10,
                        supportsReplacement: true,
                        requiresDisplayName: false,
                    ),
                ],
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
