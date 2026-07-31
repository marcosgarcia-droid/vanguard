<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use InvalidArgumentException;
use JsonException;

final class IntelbrasFacialCredentialResponseInterpreter
{
    public const MAX_RESPONSE_BYTES = 16_384;

    public const MAX_JSON_DEPTH = 16;

    public function interpret(
        string $responseBody
    ): IntelbrasFacialCredentialResponse {
        if (! $this->isStructurallySafe($responseBody)) {
            return IntelbrasFacialCredentialResponse::invalidResponse();
        }

        $normalized = trim($responseBody);

        if ($normalized === '') {
            return IntelbrasFacialCredentialResponse::invalidResponse();
        }

        if ($normalized === 'OK') {
            return IntelbrasFacialCredentialResponse::succeeded();
        }

        if (
            str_starts_with($normalized, '{')
            || str_starts_with($normalized, '[')
        ) {
            return $this->interpretJson($normalized);
        }

        return IntelbrasFacialCredentialResponse::failed();
    }

    private function isStructurallySafe(
        string $responseBody
    ): bool {
        if (
            $responseBody === ''
            || strlen($responseBody) > self::MAX_RESPONSE_BYTES
        ) {
            return false;
        }

        return preg_match(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',
            $responseBody
        ) !== 1;
    }

    private function interpretJson(
        string $responseBody
    ): IntelbrasFacialCredentialResponse {
        if (! str_starts_with($responseBody, '{')) {
            return IntelbrasFacialCredentialResponse::invalidResponse();
        }

        try {
            $decoded = json_decode(
                $responseBody,
                true,
                self::MAX_JSON_DEPTH,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return IntelbrasFacialCredentialResponse::invalidResponse();
        }

        if (! is_array($decoded)) {
            return IntelbrasFacialCredentialResponse::invalidResponse();
        }

        $code = $decoded['code'] ?? null;

        if (
            $code !== null
            && (
                ! is_int($code)
                || $code < 0
                || $code
                    > IntelbrasFacialCredentialResponse::MAX_VENDOR_CODE
            )
        ) {
            return IntelbrasFacialCredentialResponse::invalidResponse();
        }

        try {
            $failCodes = $this->extractFailCodes($decoded);
        } catch (InvalidArgumentException) {
            return IntelbrasFacialCredentialResponse::invalidResponse();
        }

        if (
            $code
                === IntelbrasFacialCredentialResponse::BATCH_PROCESS_ERROR_CODE
            && in_array(
                IntelbrasFacialCredentialResponse::DUPLICATE_PHOTO_FAIL_CODE,
                $failCodes,
                true
            )
        ) {
            return IntelbrasFacialCredentialResponse::duplicatePhoto(
                $failCodes
            );
        }

        return IntelbrasFacialCredentialResponse::failed(
            code: $code,
            failCodes: $failCodes,
        );
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return list<int>
     */
    private function extractFailCodes(
        array $decoded
    ): array {
        if (! array_key_exists('detail', $decoded)) {
            return [];
        }

        $detail = $decoded['detail'];

        if (
            ! is_array($detail)
            || ($detail !== [] && array_is_list($detail))
        ) {
            throw new InvalidArgumentException(
                'O detalhe da resposta facial é inválido.'
            );
        }

        if (! array_key_exists('FailCodes', $detail)) {
            $this->validateFailCount(
                $detail,
                []
            );

            return [];
        }

        $failCodes = $detail['FailCodes'];

        if (
            ! is_array($failCodes)
            || ! array_is_list($failCodes)
            || count($failCodes)
                > IntelbrasFacialCredentialResponse::MAX_FAIL_CODES
        ) {
            throw new InvalidArgumentException(
                'A lista de códigos de falha é inválida.'
            );
        }

        foreach ($failCodes as $failCode) {
            if (
                ! is_int($failCode)
                || $failCode < 0
                || $failCode
                    > IntelbrasFacialCredentialResponse::MAX_VENDOR_CODE
            ) {
                throw new InvalidArgumentException(
                    'A resposta contém um código de falha inválido.'
                );
            }
        }

        $this->validateFailCount(
            $detail,
            $failCodes
        );

        return $failCodes;
    }

    /**
     * @param  array<string, mixed>  $detail
     * @param  list<int>  $failCodes
     */
    private function validateFailCount(
        array $detail,
        array $failCodes,
    ): void {
        if (! array_key_exists('FailCount', $detail)) {
            return;
        }

        $failCount = $detail['FailCount'];

        if (
            ! is_int($failCount)
            || $failCount < 0
            || $failCount !== count($failCodes)
        ) {
            throw new InvalidArgumentException(
                'A quantidade de falhas da resposta é inválida.'
            );
        }
    }
}
