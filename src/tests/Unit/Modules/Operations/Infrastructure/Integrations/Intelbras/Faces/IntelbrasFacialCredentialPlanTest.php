<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityProfile;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialDeviceFamily;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialItem;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialPlan;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialPhotoDescriptor;
use InvalidArgumentException;
use Tests\TestCase;

final class IntelbrasFacialCredentialPlanTest extends TestCase
{
    public function test_it_creates_a_safe_semantic_plan(): void
    {
        $plan = new IntelbrasFacialCredentialPlan(
            compatibility: $this->batchProfile(),
            operation: IntelbrasFacialCredentialOperation::Register,
            items: [
                $this->item(
                    externalUserId: 'synthetic-001',
                    hashCharacter: 'a',
                ),
                $this->item(
                    externalUserId: 'synthetic-002',
                    hashCharacter: 'b',
                ),
            ],
        );

        $safe = $plan->toSafeArray();

        $this->assertSame(2, $plan->itemCount());
        $this->assertSame('batch_capable', $safe['compatibility']['family']);
        $this->assertSame('SYNTHETIC-BATCH', $safe['compatibility']['model']);
        $this->assertSame('SYNTHETIC-2026.04', $safe['compatibility']['firmware']);
        $this->assertSame('register', $safe['operation']);
        $this->assertSame(2, $safe['item_count']);

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/D',
            $safe['plan_fingerprint']
        );

        $safeJson = json_encode(
            $safe,
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            'synthetic-001',
            $safeJson
        );

        $this->assertStringNotContainsString(
            str_repeat('a', 64),
            $safeJson
        );
    }

    public function test_fingerprint_is_deterministic_and_changes_with_metadata(): void
    {
        $first = new IntelbrasFacialCredentialPlan(
            compatibility: $this->batchProfile(),
            operation: IntelbrasFacialCredentialOperation::Register,
            items: [
                $this->item(
                    externalUserId: 'synthetic-003',
                    hashCharacter: 'c',
                ),
            ],
        );

        $second = new IntelbrasFacialCredentialPlan(
            compatibility: $this->batchProfile(),
            operation: IntelbrasFacialCredentialOperation::Register,
            items: [
                $this->item(
                    externalUserId: 'synthetic-003',
                    hashCharacter: 'c',
                ),
            ],
        );

        $changed = new IntelbrasFacialCredentialPlan(
            compatibility: $this->batchProfile(),
            operation: IntelbrasFacialCredentialOperation::Register,
            items: [
                $this->item(
                    externalUserId: 'synthetic-003',
                    hashCharacter: 'd',
                ),
            ],
        );

        $this->assertSame(
            $first->safeFingerprint(),
            $second->safeFingerprint()
        );

        $this->assertNotSame(
            $first->safeFingerprint(),
            $changed->safeFingerprint()
        );
    }

    public function test_it_enforces_the_profile_item_limit(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new IntelbrasFacialCredentialPlan(
            compatibility: new IntelbrasFacialCredentialCompatibilityProfile(
                family: IntelbrasFacialCredentialDeviceFamily::BatchCapable,
                model: 'SYNTHETIC-LIMITED',
                firmware: 'SYNTHETIC-2026.04',
                maxItems: 1,
                supportsReplacement: true,
                requiresDisplayName: false,
            ),
            operation: IntelbrasFacialCredentialOperation::Register,
            items: [
                $this->item('synthetic-004', 'e'),
                $this->item('synthetic-005', 'f'),
            ],
        );
    }

    public function test_single_person_family_accepts_exactly_one_item(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new IntelbrasFacialCredentialPlan(
            compatibility: new IntelbrasFacialCredentialCompatibilityProfile(
                family: IntelbrasFacialCredentialDeviceFamily::SinglePerson,
                model: 'SYNTHETIC-SINGLE',
                firmware: 'SYNTHETIC-2026.04',
                maxItems: 1,
                supportsReplacement: false,
                requiresDisplayName: true,
            ),
            operation: IntelbrasFacialCredentialOperation::Register,
            items: [
                $this->item(
                    'synthetic-006',
                    '1',
                    'Pessoa sintética 6'
                ),
                $this->item(
                    'synthetic-007',
                    '2',
                    'Pessoa sintética 7'
                ),
            ],
        );
    }

    public function test_it_requires_display_name_when_profile_requires_it(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new IntelbrasFacialCredentialPlan(
            compatibility: new IntelbrasFacialCredentialCompatibilityProfile(
                family: IntelbrasFacialCredentialDeviceFamily::SinglePerson,
                model: 'SYNTHETIC-NAMED',
                firmware: 'SYNTHETIC-2026.04',
                maxItems: 1,
                supportsReplacement: false,
                requiresDisplayName: true,
            ),
            operation: IntelbrasFacialCredentialOperation::Register,
            items: [
                $this->item('synthetic-008', '3'),
            ],
        );
    }

    public function test_it_blocks_replacement_when_profile_does_not_support_it(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new IntelbrasFacialCredentialPlan(
            compatibility: new IntelbrasFacialCredentialCompatibilityProfile(
                family: IntelbrasFacialCredentialDeviceFamily::BatchCapable,
                model: 'SYNTHETIC-NO-REPLACE',
                firmware: 'SYNTHETIC-2026.04',
                maxItems: 10,
                supportsReplacement: false,
                requiresDisplayName: false,
            ),
            operation: IntelbrasFacialCredentialOperation::Replace,
            items: [
                $this->item('synthetic-009', '4'),
            ],
        );
    }

    public function test_single_person_profile_must_have_limit_one(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new IntelbrasFacialCredentialCompatibilityProfile(
            family: IntelbrasFacialCredentialDeviceFamily::SinglePerson,
            model: 'SYNTHETIC-INVALID',
            firmware: 'SYNTHETIC-2026.04',
            maxItems: 2,
            supportsReplacement: false,
            requiresDisplayName: true,
        );
    }

    private function batchProfile(): IntelbrasFacialCredentialCompatibilityProfile
    {
        return new IntelbrasFacialCredentialCompatibilityProfile(
            family: IntelbrasFacialCredentialDeviceFamily::BatchCapable,
            model: 'SYNTHETIC-BATCH',
            firmware: 'SYNTHETIC-2026.04',
            maxItems: 10,
            supportsReplacement: true,
            requiresDisplayName: false,
        );
    }

    private function item(
        string $externalUserId,
        string $hashCharacter,
        ?string $displayName = null,
    ): IntelbrasFacialCredentialItem {
        return new IntelbrasFacialCredentialItem(
            externalUserId: $externalUserId,
            photo: new IntelbrasFacialPhotoDescriptor(
                sha256: str_repeat(
                    $hashCharacter,
                    64
                ),
                byteLength: 50_000,
                width: 500,
                height: 500,
            ),
            displayName: $displayName,
        );
    }
}
