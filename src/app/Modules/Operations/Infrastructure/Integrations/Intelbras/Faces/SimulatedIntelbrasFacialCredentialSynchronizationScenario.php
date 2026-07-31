<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use JsonException;

enum SimulatedIntelbrasFacialCredentialSynchronizationScenario: string
{
    case Succeeded = 'succeeded';

    case DuplicatePhoto = 'duplicate_photo';

    case Failed = 'failed';

    case InvalidResponse = 'invalid_response';

    /**
     * @throws JsonException
     */
    public function interpretUsing(
        IntelbrasFacialCredentialResponseInterpreter $interpreter
    ): IntelbrasFacialCredentialResponse {
        return $interpreter->interpret(
            $this->syntheticResponseBody()
        );
    }

    /**
     * @throws JsonException
     */
    private function syntheticResponseBody(): string
    {
        return match ($this) {
            self::Succeeded => 'OK',

            self::DuplicatePhoto => json_encode(
                [
                    'code' => IntelbrasFacialCredentialResponse::BATCH_PROCESS_ERROR_CODE,
                    'detail' => [
                        'FailCodes' => [
                            IntelbrasFacialCredentialResponse::DUPLICATE_PHOTO_FAIL_CODE,
                        ],
                        'FailCount' => 1,
                    ],
                    'message' => 'Synthetic batch failure',
                ],
                JSON_THROW_ON_ERROR
            ),

            self::Failed => json_encode(
                [
                    'code' => 999_999,
                    'detail' => [
                        'FailCodes' => [
                            888_888,
                        ],
                        'FailCount' => 1,
                    ],
                    'message' => 'Synthetic rejected operation',
                ],
                JSON_THROW_ON_ERROR
            ),

            self::InvalidResponse => "OK\x00",
        };
    }
}
