<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityProfile;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialDeviceFamily;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialItem;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialPlan;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialResponse;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizationResult;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizationStatus;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialPhotoDescriptor;
use Tests\TestCase;

final class IntelbrasFacialCredentialSynchronizationResultTest extends TestCase
{
    public function test_it_represents_a_fail_closed_blocked_result(): void
    {
        $plan = $this->plan();

        $result =
            IntelbrasFacialCredentialSynchronizationResult::blocked(
                $plan
            );

        $this->assertSame(
            IntelbrasFacialCredentialSynchronizationStatus::Blocked,
            $result->status
        );

        $this->assertNull($result->planFingerprint);
        $this->assertNull($result->response);
        $this->assertFalse($result->transportAttempted);
        $this->assertTrue($result->requiresAttention());

        $safe = $result->toSafeArray();

        $this->assertSame('blocked', $safe['status']);
        $this->assertSame(
            'batch_capable',
            $safe['compatibility']['family']
        );
        $this->assertSame('register', $safe['operation']);
        $this->assertSame(1, $safe['item_count']);
        $this->assertNull($safe['plan_fingerprint']);
        $this->assertFalse($safe['transport_attempted']);
    }

    public function test_it_represents_a_successful_simulation(): void
    {
        $plan = $this->plan();

        $result =
            IntelbrasFacialCredentialSynchronizationResult::simulated(
                plan: $plan,
                response: IntelbrasFacialCredentialResponse::succeeded(),
            );

        $this->assertSame(
            IntelbrasFacialCredentialSynchronizationStatus::Simulated,
            $result->status
        );

        $this->assertSame(
            $plan->safeFingerprint(),
            $result->planFingerprint
        );

        $this->assertTrue(
            $result->wasSimulatedSuccessfully()
        );

        $this->assertFalse($result->requiresAttention());
        $this->assertFalse($result->transportAttempted);
    }

    public function test_duplicate_photo_requires_attention(): void
    {
        $result =
            IntelbrasFacialCredentialSynchronizationResult::simulated(
                plan: $this->plan(),
                response: IntelbrasFacialCredentialResponse::duplicatePhoto(),
            );

        $this->assertTrue($result->isDuplicatePhoto());
        $this->assertTrue($result->requiresAttention());

        $this->assertFalse(
            $result->wasSimulatedSuccessfully()
        );
    }

    public function test_safe_result_does_not_expose_person_or_photo_hash(): void
    {
        $result =
            IntelbrasFacialCredentialSynchronizationResult::simulated(
                plan: $this->plan(),
                response: IntelbrasFacialCredentialResponse::succeeded(),
            );

        $safeJson = json_encode(
            $result->toSafeArray(),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            'synthetic-result-001',
            $safeJson
        );

        $this->assertStringNotContainsString(
            str_repeat('a', 64),
            $safeJson
        );
    }

    private function plan(): IntelbrasFacialCredentialPlan
    {
        return new IntelbrasFacialCredentialPlan(
            compatibility: new IntelbrasFacialCredentialCompatibilityProfile(
                family: IntelbrasFacialCredentialDeviceFamily::BatchCapable,
                model: 'SYNTHETIC-RESULT',
                firmware: 'SYNTHETIC-2026.04',
                maxItems: 10,
                supportsReplacement: true,
                requiresDisplayName: false,
            ),
            operation: IntelbrasFacialCredentialOperation::Register,
            items: [
                new IntelbrasFacialCredentialItem(
                    externalUserId: 'synthetic-result-001',
                    photo: new IntelbrasFacialPhotoDescriptor(
                        sha256: str_repeat('a', 64),
                        byteLength: 50_000,
                        width: 500,
                        height: 500,
                    ),
                ),
            ],
        );
    }
}
