<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialResponse;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialResponseStatus;
use InvalidArgumentException;
use Tests\TestCase;

final class IntelbrasFacialCredentialResponseTest extends TestCase
{
    public function test_it_represents_a_safe_success(): void
    {
        $response =
            IntelbrasFacialCredentialResponse::succeeded();

        $this->assertTrue($response->wasSuccessful());
        $this->assertFalse($response->isDuplicatePhoto());

        $this->assertSame(
            [
                'status' => 'succeeded',
                'code' => null,
                'fail_codes' => [],
                'message' => 'A operação facial foi aceita pelo equipamento.',
            ],
            $response->toSafeArray()
        );
    }

    public function test_it_represents_a_documented_duplicate_photo(): void
    {
        $response =
            IntelbrasFacialCredentialResponse::duplicatePhoto();

        $this->assertSame(
            IntelbrasFacialCredentialResponseStatus::DuplicatePhoto,
            $response->status
        );

        $this->assertSame(
            IntelbrasFacialCredentialResponse::BATCH_PROCESS_ERROR_CODE,
            $response->code
        );

        $this->assertSame(
            [
                IntelbrasFacialCredentialResponse::DUPLICATE_PHOTO_FAIL_CODE,
            ],
            $response->failCodes
        );

        $this->assertTrue($response->isDuplicatePhoto());
        $this->assertTrue($response->failedSafely());
    }

    public function test_invalid_response_preserves_no_untrusted_data(): void
    {
        $response =
            IntelbrasFacialCredentialResponse::invalidResponse();

        $this->assertSame(
            [
                'status' => 'invalid_response',
                'code' => null,
                'fail_codes' => [],
                'message' => 'O equipamento retornou uma resposta facial inválida.',
            ],
            $response->toSafeArray()
        );
    }

    public function test_generic_failure_cannot_masquerade_as_duplicate_photo(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        IntelbrasFacialCredentialResponse::failed(
            code: IntelbrasFacialCredentialResponse::BATCH_PROCESS_ERROR_CODE,
            failCodes: [
                IntelbrasFacialCredentialResponse::DUPLICATE_PHOTO_FAIL_CODE,
            ],
        );
    }
}
