<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\UI\Filament\Resources\EmployeeRecords;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Actions\EmployeeFacialCredentialSynchronizationCreationAudit;
use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationResult;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

final class EmployeeFacialCredentialSynchronizationCreationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_creation_without_internal_identifiers_or_sensitive_values(): void
    {
        [
            $employee,
            $user,
            $device,
        ] = $this->fixture();

        $synchronizationId =
            (string) Str::uuid();

        EmployeeFacialCredentialSynchronizationCreationAudit::record(
            employee: $employee,
            user: $user,
            device: $device,
            operation: IntelbrasFacialCredentialOperation::Register,
            result: CreateFacialCredentialSynchronizationResult::created(
                synchronizationId: $synchronizationId,
                version: 1,
            ),
        );

        $activity =
            Activity::query()
                ->where(
                    'subject_type',
                    EmployeeRecord::class
                )
                ->where(
                    'subject_id',
                    $employee->getKey()
                )
                ->where(
                    'event',
                    'employee_facial_credential_synchronization_created'
                )
                ->firstOrFail();

        self::assertSame(
            (string) $user->getKey(),
            (string) $activity->causer_id
        );

        self::assertSame(
            'Intenção de sincronização facial criada',
            $activity->description
        );

        self::assertSame(
            'Intenção criada',
            $activity->properties->get(
                'resultado'
            )
        );

        self::assertSame(
            'Cadastro',
            $activity->properties->get(
                'operação'
            )
        );

        self::assertSame(
            1,
            $activity->properties->get(
                'versão'
            )
        );

        $serialized =
            $activity->properties->toJson();

        self::assertStringNotContainsString(
            $synchronizationId,
            $serialized
        );

        foreach (
            [
                'plan_fingerprint',
                'context_fingerprint',
                'source_sha256',
                'credential_username',
                'credential_password',
                'raw_payload',
            ] as $sensitive
        ) {
            self::assertStringNotContainsString(
                $sensitive,
                $serialized
            );
        }
    }

    public function test_it_records_a_safe_generic_internal_failure(): void
    {
        [
            $employee,
            $user,
            $device,
        ] = $this->fixture();

        EmployeeFacialCredentialSynchronizationCreationAudit::failure(
            employee: $employee,
            user: $user,
            device: $device,
        );

        $activity =
            Activity::query()
                ->where(
                    'subject_type',
                    EmployeeRecord::class
                )
                ->where(
                    'subject_id',
                    $employee->getKey()
                )
                ->where(
                    'event',
                    'employee_facial_credential_synchronization_failed'
                )
                ->firstOrFail();

        self::assertSame(
            'Falha ao preparar intenção de sincronização facial',
            $activity->description
        );

        self::assertSame(
            'Falha interna ao preparar a intenção',
            $activity->properties->get(
                'resultado'
            )
        );

        $serialized =
            $activity->properties->toJson();

        self::assertStringNotContainsString(
            'exception',
            mb_strtolower($serialized)
        );

        self::assertStringNotContainsString(
            'trace',
            mb_strtolower($serialized)
        );
    }

    /**
     * @return array{
     *     0: EmployeeRecord,
     *     1: User,
     *     2: AccessDeviceRecord
     * }
     */
    private function fixture(): array
    {
        $tenant =
            TenantRecord::query()->create([
                'id' => (string) Str::uuid(),
                'name' => 'GRUPO SINTÉTICO A5G.2-A2 AUDIT',
                'status' => 'active',
            ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE SINTÉTICA A5G.2-A2 AUDIT LTDA',
                'display_name' => 'UNIDADE SINTÉTICA A5G.2-A2 AUDIT',
            ]);

        $employee =
            EmployeeRecord::query()->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'employee_code' => 'VIS-AUDIT-A5G2',
                'full_name' => 'VISITANTE SINTÉTICO A5G.2-A2 AUDIT',
                'status' => 'active',
            ]);

        $user = User::factory()->create([
            'name' => 'OPERADOR SINTÉTICO A5G.2-A2',
        ]);

        $device =
            AccessDeviceRecord::query()->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'code' => 'FAC-AUDIT-A5G2',
                'name' => 'Leitor sintético de auditoria',
                'device_type' => 'facial_reader',
                'provider' => 'intelbras',
                'model' => 'SS 3532 MF',
                'direction' => AccessDeviceDirection::Entry,
                'status' => AccessDeviceStatus::Active,
            ]);

        return [
            $employee,
            $user,
            $device,
        ];
    }
}
