<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationSource;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceConfigurationSnapshotRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Actions\VisitorFacialCredentialSynchronizationDeviceSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class VisitorFacialCredentialSynchronizationDeviceSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_active_intelbras_facial_readers_from_the_same_scope_with_a_successful_snapshot(): void
    {
        [
            $tenant,
            $organization,
            $visitor,
        ] = $this->scopeFixture();

        $eligible =
            $this->device(
                tenant: $tenant,
                organization: $organization,
                code: 'FAC-ELIGIBLE',
            );

        $this->snapshot(
            device: $eligible,
            tenant: $tenant,
            organization: $organization,
        );

        $withoutSnapshot =
            $this->device(
                tenant: $tenant,
                organization: $organization,
                code: 'FAC-NO-SNAPSHOT',
            );

        $inactive =
            $this->device(
                tenant: $tenant,
                organization: $organization,
                code: 'FAC-INACTIVE',
                status: AccessDeviceStatus::Inactive,
            );

        $otherProvider =
            $this->device(
                tenant: $tenant,
                organization: $organization,
                code: 'FAC-OTHER-PROVIDER',
                provider: 'simulator',
            );

        $otherOrganization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'OUTRA UNIDADE SINTÉTICA LTDA',
                'display_name' => 'OUTRA UNIDADE SINTÉTICA',
            ]);

        $otherScope =
            $this->device(
                tenant: $tenant,
                organization: $otherOrganization,
                code: 'FAC-OTHER-SCOPE',
            );

        $this->snapshot(
            device: $otherScope,
            tenant: $tenant,
            organization: $otherOrganization,
        );

        $options =
            VisitorFacialCredentialSynchronizationDeviceSelection::options(
                $visitor
            );

        self::assertSame(
            [
                (string) $eligible->getKey() => 'FAC-ELIGIBLE - Leitor sintético FAC-ELIGIBLE — SS 3532 MF',
            ],
            $options
        );

        self::assertTrue(
            VisitorFacialCredentialSynchronizationDeviceSelection::isSelectable(
                $visitor,
                (string) $eligible->getKey()
            )
        );

        foreach (
            [
                $withoutSnapshot,
                $inactive,
                $otherProvider,
                $otherScope,
            ] as $blocked
        ) {
            self::assertFalse(
                VisitorFacialCredentialSynchronizationDeviceSelection::isSelectable(
                    $visitor,
                    (string) $blocked->getKey()
                )
            );
        }
    }

    public function test_it_fails_closed_when_the_visitor_has_no_scope(): void
    {
        $visitor = new VisitorRecord;

        self::assertSame(
            [],
            VisitorFacialCredentialSynchronizationDeviceSelection::options(
                $visitor
            )
        );

        self::assertFalse(
            VisitorFacialCredentialSynchronizationDeviceSelection::isSelectable(
                $visitor,
                (string) Str::uuid()
            )
        );

        self::assertSame(
            'O visitante não possui grupo empresarial e unidade válidos.',
            VisitorFacialCredentialSynchronizationDeviceSelection::unavailableReason(
                $visitor
            )
        );
    }

    public function test_it_rejects_empty_and_unknown_device_identifiers(): void
    {
        [
            ,
            ,
            $visitor,
        ] = $this->scopeFixture();

        self::assertFalse(
            VisitorFacialCredentialSynchronizationDeviceSelection::isSelectable(
                $visitor,
                ''
            )
        );

        self::assertFalse(
            VisitorFacialCredentialSynchronizationDeviceSelection::isSelectable(
                $visitor,
                (string) Str::uuid()
            )
        );
    }

    /**
     * @return array{
     *     0: TenantRecord,
     *     1: OrganizationRecord,
     *     2: VisitorRecord
     * }
     */
    private function scopeFixture(): array
    {
        $tenant =
            TenantRecord::query()->create([
                'id' => (string) Str::uuid(),
                'name' => 'GRUPO SINTÉTICO A5G.2-A2',
                'status' => 'active',
            ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE SINTÉTICA A5G.2-A2 LTDA',
                'display_name' => 'UNIDADE SINTÉTICA A5G.2-A2',
            ]);

        $visitor =
            VisitorRecord::query()->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'visitor_code' => 'VIS-A5G2-A2',
                'full_name' => 'VISITANTE SINTÉTICO A5G.2-A2',
                'status' => 'active',
            ]);

        return [
            $tenant,
            $organization,
            $visitor,
        ];
    }

    private function device(
        TenantRecord $tenant,
        OrganizationRecord $organization,
        string $code,
        AccessDeviceStatus $status =
            AccessDeviceStatus::Active,
        string $provider = 'intelbras',
    ): AccessDeviceRecord {
        return AccessDeviceRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'code' => $code,
            'name' => 'Leitor sintético '.$code,
            'device_type' => 'facial_reader',
            'provider' => $provider,
            'model' => 'SS 3532 MF',
            'direction' => AccessDeviceDirection::Entry,
            'status' => $status,
        ]);
    }

    private function snapshot(
        AccessDeviceRecord $device,
        TenantRecord $tenant,
        OrganizationRecord $organization,
    ): AccessDeviceConfigurationSnapshotRecord {
        return AccessDeviceConfigurationSnapshotRecord::query()
            ->create([
                'access_device_id' => $device->id,
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'source' => AccessDeviceConfigurationSource::Manual,
                'status' => AccessDeviceConfigurationReadStatus::Success,
                'device_model' => 'SS 3532 MF',
                'firmware_version' => '20991231',
                'configuration' => [],
                'capabilities' => [],
                'sanitized_response' => [],
                'configuration_hash' => hash(
                    'sha256',
                    'configuration-'.$device->id
                ),
                'read_at' => now(),
                'duration_ms' => 10,
                'message' => 'Leitura sintética válida.',
            ]);
    }
}
