<?php

namespace Database\Seeders;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventCollectionMode;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class VanguardAccessDeviceDemoSeeder extends Seeder
{
    private const TENANT_NAME =
        'VANGUARD LABORATÓRIO SINTÉTICO';

    private const ORGANIZATION_CODE =
        'LAB-AC-01';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'O seeder de dispositivos sintéticos só pode ser executado nos ambientes local e testing.'
            );
        }

        DB::transaction(function (): void {
            $tenant = $this->tenant();
            $organization = $this->organization(
                $tenant
            );

            foreach ($this->devices() as $data) {
                $this->persistDevice(
                    $tenant,
                    $organization,
                    $data
                );
            }
        });
    }

    private function tenant(): TenantRecord
    {
        $tenant = TenantRecord::query()
            ->where(
                'name',
                self::TENANT_NAME
            )
            ->first();

        if (! $tenant instanceof TenantRecord) {
            $tenant = new TenantRecord;
            $tenant->id = (string) Str::uuid();
        }

        $tenant->forceFill([
            'name' => self::TENANT_NAME,
            'status' => 'active',
        ])->saveQuietly();

        return $tenant;
    }

    private function organization(
        TenantRecord $tenant
    ): OrganizationRecord {
        $organization =
            OrganizationRecord::query()
                ->where(
                    'tenant_id',
                    $tenant->id
                )
                ->where(
                    'unit_code',
                    self::ORGANIZATION_CODE
                )
                ->first();

        if (
            ! $organization
            instanceof OrganizationRecord
        ) {
            $organization =
                new OrganizationRecord;

            $organization->id =
                (string) Str::uuid();
        }

        $organization->forceFill([
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'VANGUARD LABORATÓRIO SINTÉTICO LTDA',
            'display_name' => 'LABORATÓRIO DE CONTROLE DE ACESSO',
            'unit_code' => self::ORGANIZATION_CODE,
        ])->saveQuietly();

        return $organization;
    }

    /**
     * @param array{
     *     code: string,
     *     name: string,
     *     direction: AccessDeviceDirection,
     *     serial_number: string,
     *     external_id: string,
     *     scenario: string
     * } $data
     */
    private function persistDevice(
        TenantRecord $tenant,
        OrganizationRecord $organization,
        array $data
    ): void {
        $device = AccessDeviceRecord::withTrashed()
            ->where(
                'tenant_id',
                $tenant->id
            )
            ->where(
                'organization_id',
                $organization->id
            )
            ->where(
                'code',
                $data['code']
            )
            ->first();

        if (
            ! $device
            instanceof AccessDeviceRecord
        ) {
            $device =
                new AccessDeviceRecord;

            $device->id =
                (string) Str::uuid();
        }

        $device->forceFill([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'code' => $data['code'],
            'name' => $data['name'],
            'device_type' => 'facial_reader',
            'provider' => 'simulator',
            'model' => 'SIMULADOR LOCAL V1',
            'serial_number' => $data['serial_number'],
            'external_id' => $data['external_id'],
            'ip_address' => null,
            'port' => null,
            'protocol' => 'http',
            'auth_type' => 'digest',
            'credential_username' => null,
            'credential_password' => null,
            'direction' => $data['direction'],
            'status' => AccessDeviceStatus::Active,
            'settings' => [
                'simulator_scenario' => $data['scenario'],
                'timezone' => 'America/Sao_Paulo',
                'event_collection_mode' => AccessEventCollectionMode::Disabled
                    ->value,
                'polling_interval_seconds' => 30,
                'recovery_window_minutes' => 5,
                'clock_tolerance_seconds' => 60,
                'verify_tls' => false,
            ],
            'notes' => 'Dispositivo totalmente sintético para desenvolvimento e demonstração. Não representa equipamento, endereço, credencial ou configuração real.',
            'deleted_at' => null,
        ])->saveQuietly();
    }

    /**
     * @return array<int, array{
     *     code: string,
     *     name: string,
     *     direction: AccessDeviceDirection,
     *     serial_number: string,
     *     external_id: string,
     *     scenario: string
     * }>
     */
    private function devices(): array
    {
        return [
            [
                'code' => 'FAC-SIM-ENT-01',
                'name' => 'Facial simulado entrada 01',
                'direction' => AccessDeviceDirection::Entry,
                'serial_number' => 'SYNTHETIC-ENTRY-0001',
                'external_id' => 'simulator-demo-entry-01',
                'scenario' => 'success',
            ],
            [
                'code' => 'FAC-SIM-ENT-02',
                'name' => 'Facial simulado entrada 02',
                'direction' => AccessDeviceDirection::Entry,
                'serial_number' => 'SYNTHETIC-ENTRY-0002',
                'external_id' => 'simulator-demo-entry-02',
                'scenario' => 'partial',
            ],
            [
                'code' => 'FAC-SIM-SAI-01',
                'name' => 'Facial simulado saída 01',
                'direction' => AccessDeviceDirection::Exit,
                'serial_number' => 'SYNTHETIC-EXIT-0001',
                'external_id' => 'simulator-demo-exit-01',
                'scenario' => 'success',
            ],
            [
                'code' => 'FAC-SIM-SAI-02',
                'name' => 'Facial simulado saída 02',
                'direction' => AccessDeviceDirection::Exit,
                'serial_number' => 'SYNTHETIC-EXIT-0002',
                'external_id' => 'simulator-demo-exit-02',
                'scenario' => 'failed',
            ],
        ];
    }
}
