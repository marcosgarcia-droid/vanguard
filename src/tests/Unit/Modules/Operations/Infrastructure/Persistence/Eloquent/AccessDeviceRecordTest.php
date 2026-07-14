<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AccessDeviceRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_device_configuration_and_encrypts_credentials(): void
    {
        [$tenant, $organization] = $this->createScope();

        $device = AccessDeviceRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'code' => 'FAC-ENT-01',
            'name' => 'Facial entrada 01',
            'device_type' => 'facial_reader',
            'provider' => 'intelbras',
            'model' => 'SS 3532 MF W',
            'serial_number' => 'DEMO-SERIAL-001',
            'external_id' => 'device-demo-001',
            'ip_address' => '192.168.10.21',
            'port' => 80,
            'protocol' => 'http',
            'auth_type' => 'digest',
            'credential_username' => 'admin',
            'credential_password' => 'secret-demo',
            'direction' => AccessDeviceDirection::Entry,
            'status' => AccessDeviceStatus::Active,
            'settings' => [
                'timezone' => 'America/Sao_Paulo',
            ],
        ]);

        $loaded = AccessDeviceRecord::query()
            ->with(['tenant', 'organization'])
            ->findOrFail($device->id);

        $this->assertNotEmpty($loaded->id);
        $this->assertTrue($loaded->tenant->is($tenant));
        $this->assertTrue($loaded->organization->is($organization));

        $this->assertSame(
            AccessDeviceDirection::Entry,
            $loaded->direction
        );

        $this->assertSame(
            AccessDeviceStatus::Active,
            $loaded->status
        );

        $this->assertSame('admin', $loaded->credential_username);
        $this->assertSame(
            'secret-demo',
            $loaded->credential_password
        );

        $this->assertTrue($loaded->hasConfiguredCredentials());

        $this->assertSame([
            'timezone' => 'America/Sao_Paulo',
        ], $loaded->settings);

        $raw = DB::table('access_devices')
            ->where('id', $device->id)
            ->first();

        $this->assertNotNull($raw);

        $this->assertNotSame(
            'admin',
            $raw->credential_username
        );

        $this->assertNotSame(
            'secret-demo',
            $raw->credential_password
        );
    }

    public function test_it_does_not_expose_credentials_in_activity_changes(): void
    {
        [$tenant, $organization] = $this->createScope();

        $device = AccessDeviceRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'code' => 'FAC-SAI-01',
            'name' => 'Facial saída 01',
            'provider' => 'intelbras',
            'direction' => AccessDeviceDirection::Exit,
            'status' => AccessDeviceStatus::Active,
            'credential_username' => 'admin',
            'credential_password' => 'first-secret',
        ]);

        $device->update([
            'name' => 'Facial saída principal',
            'credential_password' => 'second-secret',
        ]);

        $activity = Activity::query()
            ->where('subject_type', AccessDeviceRecord::class)
            ->where('subject_id', $device->id)
            ->latest('id')
            ->firstOrFail();

        $changes = $activity->attribute_changes?->toArray() ?? [];

        $attributes = $changes['attributes'] ?? [];
        $old = $changes['old'] ?? [];

        $this->assertSame(
            'Facial saída principal',
            $attributes['name'] ?? null
        );

        $this->assertSame(
            'Facial saída 01',
            $old['name'] ?? null
        );

        $this->assertArrayNotHasKey(
            'credential_username',
            $attributes
        );

        $this->assertArrayNotHasKey(
            'credential_password',
            $attributes
        );

        $this->assertArrayNotHasKey(
            'credential_password',
            $old
        );
    }

    /**
     * @return array{TenantRecord, OrganizationRecord}
     */
    private function createScope(): array
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

        return [$tenant, $organization];
    }
}
