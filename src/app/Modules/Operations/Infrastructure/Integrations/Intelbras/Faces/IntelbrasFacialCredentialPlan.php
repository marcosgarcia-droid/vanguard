<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use InvalidArgumentException;
use JsonException;

final readonly class IntelbrasFacialCredentialPlan
{
    /**
     * @var list<IntelbrasFacialCredentialItem>
     */
    public array $items;

    /**
     * @param  list<IntelbrasFacialCredentialItem>  $items
     */
    public function __construct(
        public IntelbrasFacialCredentialCompatibilityProfile $compatibility,
        public IntelbrasFacialCredentialOperation $operation,
        array $items,
    ) {
        if (
            ! array_is_list($items)
            || $items === []
        ) {
            throw new InvalidArgumentException(
                'O plano facial deve possuir uma lista de credenciais.'
            );
        }

        foreach ($items as $item) {
            if (! $item instanceof IntelbrasFacialCredentialItem) {
                throw new InvalidArgumentException(
                    'O plano facial possui um item inválido.'
                );
            }
        }

        $itemCount = count($items);

        if ($itemCount > $compatibility->maxItems) {
            throw new InvalidArgumentException(
                'O plano facial excede o limite do perfil de compatibilidade.'
            );
        }

        if (
            $compatibility->family
                === IntelbrasFacialCredentialDeviceFamily::SinglePerson
            && $itemCount !== 1
        ) {
            throw new InvalidArgumentException(
                'A família individual aceita somente uma credencial por plano.'
            );
        }

        if (
            $operation
                === IntelbrasFacialCredentialOperation::Replace
            && ! $compatibility->supportsReplacement
        ) {
            throw new InvalidArgumentException(
                'O perfil de compatibilidade não permite substituir a foto.'
            );
        }

        if ($compatibility->requiresDisplayName) {
            foreach ($items as $item) {
                if (! $item->hasDisplayName()) {
                    throw new InvalidArgumentException(
                        'O perfil de compatibilidade exige o nome da pessoa.'
                    );
                }
            }
        }

        $this->items = array_values($items);
    }

    public function itemCount(): int
    {
        return count($this->items);
    }

    /**
     * @throws JsonException
     */
    public function safeFingerprint(): string
    {
        $json = json_encode(
            $this->fingerprintMaterial(),
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
        );

        return hash('sha256', $json);
    }

    /**
     * @return array{
     *     compatibility: array{
     *         family: string,
     *         model: string,
     *         firmware: string,
     *         max_items: int,
     *         supports_replacement: bool,
     *         requires_display_name: bool
     *     },
     *     operation: string,
     *     item_count: int,
     *     plan_fingerprint: string
     * }
     *
     * @throws JsonException
     */
    public function toSafeArray(): array
    {
        return [
            'compatibility' => $this->compatibility->toSafeArray(),
            'operation' => $this->operation->value,
            'item_count' => $this->itemCount(),
            'plan_fingerprint' => $this->safeFingerprint(),
        ];
    }

    /**
     * @return array{
     *     compatibility: array<string, bool|int|string>,
     *     operation: string,
     *     items: list<array<string, mixed>>
     * }
     */
    private function fingerprintMaterial(): array
    {
        return [
            'compatibility' => $this->compatibility->toSafeArray(),
            'operation' => $this->operation->value,
            'items' => array_map(
                static fn (
                    IntelbrasFacialCredentialItem $item
                ): array => $item->fingerprintMaterial(),
                $this->items
            ),
        ];
    }
}
