<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use InvalidArgumentException;

final readonly class IntelbrasFacialCredentialCompatibilityProfile
{
    public const MAX_IDENTIFIER_BYTES = 120;

    public const MAX_ITEMS = 10;

    public string $model;

    public string $firmware;

    public function __construct(
        public IntelbrasFacialCredentialDeviceFamily $family,
        string $model,
        string $firmware,
        public int $maxItems,
        public bool $supportsReplacement,
        public bool $requiresDisplayName,
    ) {
        $this->model = $this->normalizeIdentifier(
            value: $model,
            field: 'modelo',
        );

        $this->firmware = $this->normalizeIdentifier(
            value: $firmware,
            field: 'firmware',
        );

        if ($maxItems < 1 || $maxItems > self::MAX_ITEMS) {
            throw new InvalidArgumentException(
                'A quantidade máxima de credenciais faciais é inválida.'
            );
        }

        if (
            $family === IntelbrasFacialCredentialDeviceFamily::SinglePerson
            && $maxItems !== 1
        ) {
            throw new InvalidArgumentException(
                'A família individual deve aceitar exatamente uma credencial.'
            );
        }
    }

    /**
     * @return array{
     *     family: string,
     *     model: string,
     *     firmware: string,
     *     max_items: int,
     *     supports_replacement: bool,
     *     requires_display_name: bool
     * }
     */
    public function toSafeArray(): array
    {
        return [
            'family' => $this->family->value,
            'model' => $this->model,
            'firmware' => $this->firmware,
            'max_items' => $this->maxItems,
            'supports_replacement' => $this->supportsReplacement,
            'requires_display_name' => $this->requiresDisplayName,
        ];
    }

    private function normalizeIdentifier(
        string $value,
        string $field,
    ): string {
        $normalized = trim($value);

        if (
            $normalized === ''
            || strlen($normalized) > self::MAX_IDENTIFIER_BYTES
            || preg_match('/[\x00-\x1F\x7F]/', $normalized) === 1
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'O %s do perfil de compatibilidade é inválido.',
                    $field
                )
            );
        }

        return $normalized;
    }
}
