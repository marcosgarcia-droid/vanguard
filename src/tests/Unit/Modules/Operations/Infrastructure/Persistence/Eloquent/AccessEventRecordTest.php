<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessEventRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_relates_a_facial_event_to_device_visitor_and_visit(): void
    {
        $tenant = TenantRecord::query()->create([
            'name' => 'GRUPO DEMONSTRAÇÃO',
            'status' => 'active',
        ]);

        $organization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'UNIDADE DEMONSTRAÇÃO LTDA',
            'display_name' => 'UNIDADE DEMONSTRAÇÃO',
        ]);

        $device = AccessDeviceRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'code' => 'FAC-ENT-01',
            'name' => 'Facial entrada 01',
            'provider' => 'intelbras',
            'direction' => AccessDeviceDirection::Entry,
            'status' => AccessDeviceStatus::Active,
        ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'Pessoa Visitante',
            'status' => VisitorStatus::Active,
            'photo_disk' => 'local',
        ]);

        $visit = VisitRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'status' => VisitStatus::Authorized,
            'purpose' => 'Reunião operacional',
            'expected_start_at' => now(),
        ]);

        $event = AccessEventRecord::query()->create([
            'access_device_id' => $device->id,
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'visit_id' => $visit->id,
            'external_event_id' => 'intelbras-event-001',
            'external_person_id' => 'visitor-external-001',
            'event_type' => 'face_recognition',
            'direction' => AccessEventDirection::Entry,
            'occurred_at' => new DateTimeImmutable(
                '2026-07-14 10:15:00'
            ),
            'status' => AccessEventStatus::Received,
            'raw_payload' => [
                'deviceId' => 'device-demo-001',
                'personId' => 'visitor-external-001',
            ],
        ]);

        $loaded = AccessEventRecord::query()
            ->with([
                'accessDevice',
                'tenant',
                'organization',
                'visitor',
                'visit',
            ])
            ->findOrFail($event->id);

        $this->assertNotEmpty($loaded->id);
        $this->assertTrue($loaded->accessDevice->is($device));
        $this->assertTrue($loaded->tenant->is($tenant));
        $this->assertTrue(
            $loaded->organization->is($organization)
        );
        $this->assertTrue($loaded->visitor->is($visitor));
        $this->assertTrue($loaded->visit->is($visit));

        $this->assertSame(
            AccessEventDirection::Entry,
            $loaded->direction
        );

        $this->assertSame(
            AccessEventStatus::Received,
            $loaded->status
        );

        $this->assertSame(
            '2026-07-14 10:15:00',
            $loaded->occurred_at?->format('Y-m-d H:i:s')
        );

        $this->assertSame(
            'visitor-external-001',
            $loaded->raw_payload['personId'] ?? null
        );

        $this->assertTrue(
            $device->direction->accepts($loaded->direction)
        );
    }
}
