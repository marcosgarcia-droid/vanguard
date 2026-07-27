<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class VisitorRecordLatestFacialPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_the_latest_facial_photo_by_capture_time(): void
    {
        $tenant = TenantRecord::query()
            ->create([
                'id' => (string) Str::uuid(),
                'name' => 'GRUPO STATUS FACIAL',
                'status' => 'active',
            ]);

        $organization =
            OrganizationRecord::query()
                ->create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'status' => 'active',
                    'legal_name' => 'UNIDADE STATUS FACIAL LTDA',
                    'display_name' => 'UNIDADE STATUS FACIAL',
                    'unit_code' => 'FAC-ST-01',
                ]);

        $visitor = VisitorRecord::query()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'full_name' => 'VISITANTE STATUS FACIAL',
                'status' => VisitorStatus::Active,
                'photo_disk' => 'local',
            ]);

        $older = $visitor
            ->facialPhotos()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'source' => FacialPhotoSource::FileUpload,
                'status' => FacialPhotoStatus::Outdated,
                'captured_at' => now()->subDay(),
            ]);

        $newer = $visitor
            ->facialPhotos()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'source' => FacialPhotoSource::Webcam,
                'status' => FacialPhotoStatus::Approved,
                'captured_at' => now(),
            ]);

        $relation = $visitor
            ->latestFacialPhoto();

        $this->assertInstanceOf(
            MorphOne::class,
            $relation
        );

        $visitor->unsetRelation(
            'latestFacialPhoto'
        );

        $visitor->load(
            'latestFacialPhoto'
        );

        $this->assertNotNull(
            $visitor->latestFacialPhoto
        );

        $this->assertTrue(
            $visitor->latestFacialPhoto->is($newer)
        );

        $this->assertFalse(
            $visitor->latestFacialPhoto->is($older)
        );

        $this->assertSame(
            FacialPhotoStatus::Approved,
            $visitor->latestFacialPhoto->status
        );
    }
}
