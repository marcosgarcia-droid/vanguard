<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use InvalidArgumentException;
use JsonException;

final readonly class IntelbrasFacialCredentialRequest
{
    /**
     * @var list<IntelbrasFacialCredentialItem>
     */
    public array $items;

    /**
     * @param  list<IntelbrasFacialCredentialItem>  $items
     */
    public function __construct(
        public IntelbrasFacialCredentialTransport $transport,
        public IntelbrasFacialCredentialOperation $operation,
        array $items,
    ) {
        if (
            $items === []
            || ! array_is_list($items)
        ) {
            throw new InvalidArgumentException(
                'A requisição facial deve possuir uma lista de itens.'
            );
        }

        foreach ($items as $item) {
            if (! $item instanceof IntelbrasFacialCredentialItem) {
                throw new InvalidArgumentException(
                    'A requisição facial contém um item inválido.'
                );
            }
        }

        if (
            $transport
                === IntelbrasFacialCredentialTransport::AccessFaceBatch
            && count($items) > 10
        ) {
            throw new InvalidArgumentException(
                'O AccessFace aceita no máximo dez faces por requisição.'
            );
        }

        if (
            $transport
                === IntelbrasFacialCredentialTransport::FaceInfoManagerSingle
        ) {
            if (count($items) !== 1) {
                throw new InvalidArgumentException(
                    'O FaceInfoManager aceita somente uma face por requisição.'
                );
            }

            if ($items[0]->displayName === null) {
                throw new InvalidArgumentException(
                    'O FaceInfoManager exige o nome do usuário.'
                );
            }
        }

        $operation->actionFor($transport);

        $this->items = $items;
    }

    public function endpointPath(): string
    {
        return $this->transport->endpointPath();
    }

    public function action(): string
    {
        return $this->operation->actionFor(
            $this->transport
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toIntelbrasPayload(): array
    {
        return match ($this->transport) {
            IntelbrasFacialCredentialTransport::AccessFaceBatch => [
                'FaceList' => array_map(
                    static fn (
                        IntelbrasFacialCredentialItem $item
                    ): array => [
                        'UserID' => $item->externalUserId,
                        'PhotoData' => [
                            $item->photo->transportBase64(),
                        ],
                    ],
                    $this->items
                ),
            ],

            IntelbrasFacialCredentialTransport::FaceInfoManagerSingle => [
                'UserID' => $this->items[0]->externalUserId,
                'Info' => [
                    'UserName' => $this->items[0]->displayName,
                    'PhotoData' => [
                        $this->items[0]->photo->transportBase64(),
                    ],
                ],
            ],
        };
    }

    /**
     * @throws JsonException
     */
    public function toDeterministicJson(): string
    {
        return json_encode(
            $this->toIntelbrasPayload(),
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * @throws JsonException
     */
    public function payloadFingerprint(): string
    {
        return hash(
            'sha256',
            $this->toDeterministicJson()
        );
    }

    /**
     * @return array{
     *     transport: string,
     *     operation: string,
     *     endpoint_path: string,
     *     action: string,
     *     item_count: int,
     *     payload_fingerprint: string,
     *     items: list<array{
     *         external_user_id: string,
     *         display_name: ?string,
     *         photo: array{
     *             byte_length: int,
     *             width: int,
     *             height: int,
     *             sha256: string
     *         }
     *     }>
     * }
     *
     * @throws JsonException
     */
    public function toSafeArray(): array
    {
        return [
            'transport' => $this->transport->value,
            'operation' => $this->operation->value,
            'endpoint_path' => $this->endpointPath(),
            'action' => $this->action(),
            'item_count' => count($this->items),
            'payload_fingerprint' => $this->payloadFingerprint(),
            'items' => array_map(
                static fn (
                    IntelbrasFacialCredentialItem $item
                ): array => $item->toSafeArray(),
                $this->items
            ),
        ];
    }
}
