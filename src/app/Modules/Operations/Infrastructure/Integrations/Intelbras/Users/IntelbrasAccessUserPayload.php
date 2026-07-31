<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Users;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;

final readonly class IntelbrasAccessUserPayload
{
    public const USER_TYPE_GENERAL = 0;

    public const USER_TYPE_BLOCKLIST = 1;

    public const USER_TYPE_GUEST = 2;

    public const USER_TYPE_PATROL = 3;

    public const USER_TYPE_VIP = 4;

    public const USER_TYPE_DISABLED = 5;

    public const AUTHORITY_ADMINISTRATOR = 1;

    public const AUTHORITY_STANDARD_USER = 2;

    public string $externalUserId;

    public string $displayName;

    public int $userType;

    public int $authority;

    /**
     * @var list<int>
     */
    public array $doorNumbers;

    /**
     * @var list<int>
     */
    public array $timeSectionNumbers;

    public DateTimeImmutable $validFrom;

    public DateTimeImmutable $validTo;

    /**
     * @param  list<int>  $doorNumbers
     * @param  list<int>  $timeSectionNumbers
     */
    public function __construct(
        string $externalUserId,
        string $displayName,
        int $userType,
        int $authority,
        array $doorNumbers,
        array $timeSectionNumbers,
        DateTimeImmutable $validFrom,
        DateTimeImmutable $validTo,
    ) {
        $this->externalUserId = self::normalizeExternalUserId(
            $externalUserId
        );

        $this->displayName = self::normalizeDisplayName(
            $displayName
        );

        if (
            $userType < self::USER_TYPE_GENERAL
            || $userType > self::USER_TYPE_DISABLED
        ) {
            throw new InvalidArgumentException(
                'O tipo de usuário Intelbras é inválido.'
            );
        }

        if (
            ! in_array(
                $authority,
                [
                    self::AUTHORITY_ADMINISTRATOR,
                    self::AUTHORITY_STANDARD_USER,
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'A autoridade do usuário Intelbras é inválida.'
            );
        }

        $normalizedDoors = self::normalizeIntegerList(
            values: $doorNumbers,
            field: 'portas',
            minimum: 1,
        );

        $normalizedTimeSections = self::normalizeIntegerList(
            values: $timeSectionNumbers,
            field: 'faixas de horário',
            minimum: 0,
        );

        if ($validTo <= $validFrom) {
            throw new InvalidArgumentException(
                'O fim da validade deve ser posterior ao início.'
            );
        }

        $this->userType = $userType;
        $this->authority = $authority;
        $this->doorNumbers = $normalizedDoors;
        $this->timeSectionNumbers = $normalizedTimeSections;
        $this->validFrom = $validFrom;
        $this->validTo = $validTo;
    }

    /**
     * Retorna somente o contrato cadastral documentado.
     *
     * Senhas, fotos, templates, embeddings e demais dados
     * biométricos não fazem parte deste objeto.
     *
     * @return array{
     *     UserList: list<array{
     *         UserID: string,
     *         UserName: string,
     *         UserType: int,
     *         Authority: int,
     *         Doors: list<int>,
     *         TimeSections: list<int>,
     *         ValidFrom: string,
     *         ValidTo: string
     *     }>
     * }
     */
    public function toIntelbrasPayload(): array
    {
        return [
            'UserList' => [
                [
                    'UserID' => $this->externalUserId,
                    'UserName' => $this->displayName,
                    'UserType' => $this->userType,
                    'Authority' => $this->authority,
                    'Doors' => $this->doorNumbers,
                    'TimeSections' => $this->timeSectionNumbers,
                    'ValidFrom' => $this->validFrom->format(
                        'Y-m-d H:i:s'
                    ),
                    'ValidTo' => $this->validTo->format(
                        'Y-m-d H:i:s'
                    ),
                ],
            ],
        ];
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

    private static function normalizeExternalUserId(
        string $value
    ): string {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new InvalidArgumentException(
                'O identificador externo não pode ficar vazio.'
            );
        }

        if (strlen($normalized) > 64) {
            throw new InvalidArgumentException(
                'O identificador externo excede o limite seguro.'
            );
        }

        if (
            preg_match(
                '/^[A-Za-z0-9._:-]+$/D',
                $normalized
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'O identificador externo possui caracteres inválidos.'
            );
        }

        return $normalized;
    }

    private static function normalizeDisplayName(
        string $value
    ): string {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new InvalidArgumentException(
                'O nome do usuário não pode ficar vazio.'
            );
        }

        if (strlen($normalized) > 128) {
            throw new InvalidArgumentException(
                'O nome do usuário excede o limite seguro.'
            );
        }

        if (
            preg_match(
                '/[\x00-\x1F\x7F]/',
                $normalized
            ) === 1
        ) {
            throw new InvalidArgumentException(
                'O nome do usuário possui caracteres de controle.'
            );
        }

        return $normalized;
    }

    /**
     * @param  list<int>  $values
     * @return list<int>
     */
    private static function normalizeIntegerList(
        array $values,
        string $field,
        int $minimum,
    ): array {
        if (
            $values === []
            || ! array_is_list($values)
        ) {
            throw new InvalidArgumentException(
                "A lista de {$field} é inválida."
            );
        }

        $normalized = [];

        foreach ($values as $value) {
            if (
                ! is_int($value)
                || $value < $minimum
            ) {
                throw new InvalidArgumentException(
                    "A lista de {$field} possui valor inválido."
                );
            }

            $normalized[$value] = $value;
        }

        ksort(
            $normalized,
            SORT_NUMERIC
        );

        return array_values($normalized);
    }
}
