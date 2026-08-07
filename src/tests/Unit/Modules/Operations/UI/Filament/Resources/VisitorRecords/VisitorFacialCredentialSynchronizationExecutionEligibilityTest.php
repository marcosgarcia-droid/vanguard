<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialCredentialSynchronizationRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoDerivativeRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Actions\VisitorFacialCredentialSynchronizationExecutionEligibility;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

final class VisitorFacialCredentialSynchronizationExecutionEligibilityTest extends TestCase
{
    public function test_it_exposes_only_the_current_pending_intention(): void
    {
        [
            $visitor,
            $photo,
            $derivative,
        ] = $this->visitorFixture();

        $eligible = $this->synchronization(
            id: '10000000-0000-4000-8000-000000000001',
            visitor: $visitor,
            photo: $photo,
            derivative: $derivative,
            status: FacialCredentialSynchronizationStatus::Pending,
            version: 2,
        );

        $olderVersion = $this->synchronization(
            id: '10000000-0000-4000-8000-000000000002',
            visitor: $visitor,
            photo: $photo,
            derivative: $derivative,
            status: FacialCredentialSynchronizationStatus::Pending,
            version: 1,
        );

        $terminal = $this->synchronization(
            id: '10000000-0000-4000-8000-000000000003',
            visitor: $visitor,
            photo: $photo,
            derivative: $derivative,
            status: FacialCredentialSynchronizationStatus::Succeeded,
            version: 3,
            deviceId: '20000000-0000-4000-8000-000000000002',
        );

        $otherScope = $this->synchronization(
            id: '10000000-0000-4000-8000-000000000004',
            visitor: $visitor,
            photo: $photo,
            derivative: $derivative,
            status: FacialCredentialSynchronizationStatus::Pending,
            version: 1,
            deviceId: '20000000-0000-4000-8000-000000000003',
            tenantId: '30000000-0000-4000-8000-000000000099',
        );

        $visitor->setRelation(
            'facialCredentialSynchronizations',
            new Collection([
                $olderVersion,
                $terminal,
                $otherScope,
                $eligible,
            ])
        );

        $synchronizations =
            VisitorFacialCredentialSynchronizationExecutionEligibility::synchronizations(
                $visitor
            );

        self::assertCount(
            1,
            $synchronizations
        );

        self::assertSame(
            $eligible->getKey(),
            $synchronizations->first()?->getKey()
        );

        self::assertTrue(
            VisitorFacialCredentialSynchronizationExecutionEligibility::hasExecutable(
                $visitor
            )
        );
    }

    public function test_it_requires_the_current_approved_photo_and_ready_derivative(): void
    {
        [
            $visitor,
            $photo,
            $derivative,
        ] = $this->visitorFixture();

        $synchronization = $this->synchronization(
            id: '10000000-0000-4000-8000-000000000005',
            visitor: $visitor,
            photo: $photo,
            derivative: $derivative,
            status: FacialCredentialSynchronizationStatus::Pending,
        );

        $visitor->setRelation(
            'facialCredentialSynchronizations',
            new Collection([
                $synchronization,
            ])
        );

        $photo->forceFill([
            'status' => null,
        ]);

        self::assertFalse(
            VisitorFacialCredentialSynchronizationExecutionEligibility::hasExecutable(
                $visitor
            )
        );

        $photo->forceFill([
            'status' => FacialPhotoStatus::Approved,
        ]);

        $derivative->forceFill([
            'status' => null,
        ]);

        self::assertFalse(
            VisitorFacialCredentialSynchronizationExecutionEligibility::hasExecutable(
                $visitor
            )
        );
    }

    public function test_it_resolves_only_an_eligible_synchronization(): void
    {
        [
            $visitor,
            $photo,
            $derivative,
        ] = $this->visitorFixture();

        $eligible = $this->synchronization(
            id: '10000000-0000-4000-8000-000000000006',
            visitor: $visitor,
            photo: $photo,
            derivative: $derivative,
            status: FacialCredentialSynchronizationStatus::Pending,
        );

        $blocked = $this->synchronization(
            id: '10000000-0000-4000-8000-000000000007',
            visitor: $visitor,
            photo: $photo,
            derivative: $derivative,
            status: FacialCredentialSynchronizationStatus::Blocked,
            deviceId: '20000000-0000-4000-8000-000000000007',
        );

        $visitor->setRelation(
            'facialCredentialSynchronizations',
            new Collection([
                $eligible,
                $blocked,
            ])
        );

        self::assertSame(
            $eligible->getKey(),
            VisitorFacialCredentialSynchronizationExecutionEligibility::resolve(
                $visitor,
                (string) $eligible->getKey()
            )?->getKey()
        );

        self::assertNull(
            VisitorFacialCredentialSynchronizationExecutionEligibility::resolve(
                $visitor,
                (string) $blocked->getKey()
            )
        );

        self::assertNull(
            VisitorFacialCredentialSynchronizationExecutionEligibility::resolve(
                $visitor,
                ' '
            )
        );
    }

    /**
     * @return array{
     *     0: VisitorRecord,
     *     1: FacialPhotoRecord,
     *     2: FacialPhotoDerivativeRecord
     * }
     */
    private function visitorFixture(): array
    {
        config()->set(
            'facial_photos.intelbras_derivative.profile',
            'intelbras_facial_credential'
        );

        config()->set(
            'facial_photos.intelbras_derivative.policy_version',
            'intelbras-facial-credential-v1'
        );

        $visitor = (new VisitorRecord)->forceFill([
            'id' => '40000000-0000-4000-8000-000000000001',
            'tenant_id' => '30000000-0000-4000-8000-000000000001',
            'organization_id' => '50000000-0000-4000-8000-000000000001',
            'name' => 'VISITANTE SINTÉTICO A5G.3-A1',
        ]);

        $photo = (new FacialPhotoRecord)->forceFill([
            'id' => '60000000-0000-4000-8000-000000000001',
            'visitor_id' => $visitor->getKey(),
            'status' => FacialPhotoStatus::Approved,
            'sha256' => str_repeat('a', 64),
        ]);

        $derivative =
            (new FacialPhotoDerivativeRecord)->forceFill([
                'id' => '70000000-0000-4000-8000-000000000001',
                'facial_photo_id' => $photo->getKey(),
                'status' => FacialPhotoDerivativeStatus::Ready,
                'profile' => 'intelbras_facial_credential',
                'policy_version' => 'intelbras-facial-credential-v1',
                'source_sha256' => str_repeat('a', 64),
            ]);

        $photo->setRelation(
            'derivatives',
            new Collection([
                $derivative,
            ])
        );

        $visitor->setRelation(
            'latestFacialPhoto',
            $photo
        );

        return [
            $visitor,
            $photo,
            $derivative,
        ];
    }

    private function synchronization(
        string $id,
        VisitorRecord $visitor,
        FacialPhotoRecord $photo,
        FacialPhotoDerivativeRecord $derivative,
        FacialCredentialSynchronizationStatus $status,
        int $version = 1,
        string $deviceId = '20000000-0000-4000-8000-000000000001',
        ?string $tenantId = null,
        ?string $organizationId = null,
    ): FacialCredentialSynchronizationRecord {
        return (new FacialCredentialSynchronizationRecord)
            ->forceFill([
                'id' => $id,
                'tenant_id' => $tenantId
                    ?? $visitor->tenant_id,
                'organization_id' => $organizationId
                    ?? $visitor->organization_id,
                'visitor_id' => $visitor->getKey(),
                'facial_photo_id' => $photo->getKey(),
                'facial_photo_derivative_id' => $derivative->getKey(),
                'access_device_id' => $deviceId,
                'operation' => 'register',
                'status' => $status,
                'version' => $version,
            ]);
    }
}
