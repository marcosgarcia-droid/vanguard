<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use InvalidArgumentException;

final readonly class IntelbrasFacialCredentialResponse
{
    public const BATCH_PROCESS_ERROR_CODE = 268_632_336;

    public const DUPLICATE_PHOTO_FAIL_CODE = 286_064_926;

    public const MAX_VENDOR_CODE = 4_294_967_295;

    public const MAX_FAIL_CODES = 32;

    /**
     * @var list<int>
     */
    public array $failCodes;

    /**
     * @param  list<int>  $failCodes
     */
    private function __construct(
        public IntelbrasFacialCredentialResponseStatus $status,
        public ?int $code,
        array $failCodes,
    ) {
        if (
            $code !== null
            && ($code < 0 || $code > self::MAX_VENDOR_CODE)
        ) {
            throw new InvalidArgumentException(
                'A resposta facial possui um código inválido.'
            );
        }

        if (
            ! array_is_list($failCodes)
            || count($failCodes) > self::MAX_FAIL_CODES
        ) {
            throw new InvalidArgumentException(
                'Os códigos de falha da resposta são inválidos.'
            );
        }

        foreach ($failCodes as $failCode) {
            if (
                ! is_int($failCode)
                || $failCode < 0
                || $failCode > self::MAX_VENDOR_CODE
            ) {
                throw new InvalidArgumentException(
                    'A resposta possui um código de falha inválido.'
                );
            }
        }

        $normalizedFailCodes = array_values(
            array_unique($failCodes)
        );

        sort($normalizedFailCodes);

        if (
            $status === IntelbrasFacialCredentialResponseStatus::Succeeded
            && ($code !== null || $normalizedFailCodes !== [])
        ) {
            throw new InvalidArgumentException(
                'Uma resposta bem-sucedida não pode conter códigos de falha.'
            );
        }

        if (
            $status
                === IntelbrasFacialCredentialResponseStatus::InvalidResponse
            && ($code !== null || $normalizedFailCodes !== [])
        ) {
            throw new InvalidArgumentException(
                'Uma resposta inválida não pode preservar dados não confiáveis.'
            );
        }

        $containsDuplicatePhoto = in_array(
            self::DUPLICATE_PHOTO_FAIL_CODE,
            $normalizedFailCodes,
            true
        );

        if (
            $status
                === IntelbrasFacialCredentialResponseStatus::DuplicatePhoto
            && (
                $code !== self::BATCH_PROCESS_ERROR_CODE
                || ! $containsDuplicatePhoto
            )
        ) {
            throw new InvalidArgumentException(
                'A resposta de foto duplicada não possui os códigos documentados.'
            );
        }

        if (
            $status === IntelbrasFacialCredentialResponseStatus::Failed
            && $code === self::BATCH_PROCESS_ERROR_CODE
            && $containsDuplicatePhoto
        ) {
            throw new InvalidArgumentException(
                'A foto duplicada deve utilizar seu status específico.'
            );
        }

        $this->failCodes = $normalizedFailCodes;
    }

    public static function succeeded(): self
    {
        return new self(
            status: IntelbrasFacialCredentialResponseStatus::Succeeded,
            code: null,
            failCodes: [],
        );
    }

    /**
     * @param  list<int>  $failCodes
     */
    public static function duplicatePhoto(
        array $failCodes = [
            self::DUPLICATE_PHOTO_FAIL_CODE,
        ],
    ): self {
        return new self(
            status: IntelbrasFacialCredentialResponseStatus::DuplicatePhoto,
            code: self::BATCH_PROCESS_ERROR_CODE,
            failCodes: $failCodes,
        );
    }

    /**
     * @param  list<int>  $failCodes
     */
    public static function failed(
        ?int $code = null,
        array $failCodes = [],
    ): self {
        return new self(
            status: IntelbrasFacialCredentialResponseStatus::Failed,
            code: $code,
            failCodes: $failCodes,
        );
    }

    public static function invalidResponse(): self
    {
        return new self(
            status: IntelbrasFacialCredentialResponseStatus::InvalidResponse,
            code: null,
            failCodes: [],
        );
    }

    public function wasSuccessful(): bool
    {
        return $this->status
            === IntelbrasFacialCredentialResponseStatus::Succeeded;
    }

    public function isDuplicatePhoto(): bool
    {
        return $this->status
            === IntelbrasFacialCredentialResponseStatus::DuplicatePhoto;
    }

    public function failedSafely(): bool
    {
        return ! $this->wasSuccessful();
    }

    /**
     * @return array{
     *     status: string,
     *     code: ?int,
     *     fail_codes: list<int>,
     *     message: string
     * }
     */
    public function toSafeArray(): array
    {
        return [
            'status' => $this->status->value,
            'code' => $this->code,
            'fail_codes' => $this->failCodes,
            'message' => $this->status->safeMessage(),
        ];
    }
}
