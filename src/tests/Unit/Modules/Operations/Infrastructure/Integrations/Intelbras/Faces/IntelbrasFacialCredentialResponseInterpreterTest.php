<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialResponse;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialResponseInterpreter;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialResponseStatus;
use Tests\TestCase;

final class IntelbrasFacialCredentialResponseInterpreterTest extends TestCase
{
    private IntelbrasFacialCredentialResponseInterpreter $interpreter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->interpreter =
            new IntelbrasFacialCredentialResponseInterpreter;
    }

    public function test_it_interprets_the_documented_ok_response(): void
    {
        $response = $this->interpreter->interpret(
            "  OK\r\n"
        );

        $this->assertSame(
            IntelbrasFacialCredentialResponseStatus::Succeeded,
            $response->status
        );

        $this->assertTrue($response->wasSuccessful());
    }

    public function test_it_interprets_the_documented_duplicate_photo(): void
    {
        $rawResponse = json_encode(
            [
                'code' => IntelbrasFacialCredentialResponse::BATCH_PROCESS_ERROR_CODE,
                'detail' => [
                    'FailCodes' => [
                        IntelbrasFacialCredentialResponse::DUPLICATE_PHOTO_FAIL_CODE,
                    ],
                    'FailCount' => 1,
                ],
                'message' => 'Batch Process Error',
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->interpreter->interpret(
            $rawResponse
        );

        $this->assertTrue($response->isDuplicatePhoto());

        $this->assertSame(
            [
                IntelbrasFacialCredentialResponse::DUPLICATE_PHOTO_FAIL_CODE,
            ],
            $response->failCodes
        );

        $safeJson = json_encode(
            $response->toSafeArray(),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            'Batch Process Error',
            $safeJson
        );

        $this->assertStringNotContainsString(
            $rawResponse,
            $safeJson
        );
    }

    public function test_unknown_json_error_fails_closed(): void
    {
        $response = $this->interpreter->interpret(
            '{"code":999999,"detail":{"FailCodes":[888888],"FailCount":1},"message":"Unknown"}'
        );

        $this->assertSame(
            IntelbrasFacialCredentialResponseStatus::Failed,
            $response->status
        );

        $this->assertFalse($response->wasSuccessful());
        $this->assertSame(999_999, $response->code);
        $this->assertSame([888_888], $response->failCodes);
    }

    public function test_json_error_without_detail_fails_closed(): void
    {
        $response = $this->interpreter->interpret(
            '{"code":123456,"message":"Rejected"}'
        );

        $this->assertSame(
            IntelbrasFacialCredentialResponseStatus::Failed,
            $response->status
        );

        $this->assertSame(123_456, $response->code);
        $this->assertSame([], $response->failCodes);
    }

    public function test_plain_text_error_is_sanitized(): void
    {
        $externalMessage =
            'Bad Request: internal vendor details';

        $response = $this->interpreter->interpret(
            $externalMessage
        );

        $this->assertSame(
            IntelbrasFacialCredentialResponseStatus::Failed,
            $response->status
        );

        $safeJson = json_encode(
            $response->toSafeArray(),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            $externalMessage,
            $safeJson
        );

        $this->assertStringNotContainsString(
            'internal vendor details',
            $safeJson
        );
    }

    public function test_malformed_json_is_invalid(): void
    {
        $response = $this->interpreter->interpret(
            '{"code":268632336'
        );

        $this->assertSame(
            IntelbrasFacialCredentialResponseStatus::InvalidResponse,
            $response->status
        );
    }

    public function test_json_list_is_invalid(): void
    {
        $response = $this->interpreter->interpret(
            '[{"code":268632336}]'
        );

        $this->assertSame(
            IntelbrasFacialCredentialResponseStatus::InvalidResponse,
            $response->status
        );
    }

    public function test_invalid_fail_codes_are_rejected(): void
    {
        $response = $this->interpreter->interpret(
            '{"code":268632336,"detail":{"FailCodes":["286064926"],"FailCount":1}}'
        );

        $this->assertSame(
            IntelbrasFacialCredentialResponseStatus::InvalidResponse,
            $response->status
        );
    }

    public function test_inconsistent_fail_count_is_invalid(): void
    {
        $response = $this->interpreter->interpret(
            '{"code":268632336,"detail":{"FailCodes":[286064926],"FailCount":2}}'
        );

        $this->assertSame(
            IntelbrasFacialCredentialResponseStatus::InvalidResponse,
            $response->status
        );
    }

    public function test_empty_control_and_oversized_responses_are_invalid(): void
    {
        $this->assertSame(
            IntelbrasFacialCredentialResponseStatus::InvalidResponse,
            $this->interpreter->interpret('')->status
        );

        $this->assertSame(
            IntelbrasFacialCredentialResponseStatus::InvalidResponse,
            $this->interpreter->interpret("OK\x00")->status
        );

        $this->assertSame(
            IntelbrasFacialCredentialResponseStatus::InvalidResponse,
            $this->interpreter->interpret(
                str_repeat(
                    'A',
                    IntelbrasFacialCredentialResponseInterpreter::MAX_RESPONSE_BYTES
                        + 1
                )
            )->status
        );
    }
}
