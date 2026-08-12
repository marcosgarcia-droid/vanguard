<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeCommand;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeException;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeRepository;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSubjectType;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoDerivativeRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class EloquentReprocessFacialPhotoDerivativeRepositoryEmployeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prepares_an_employee_failed_derivative_for_reprocessing(): void
    {
        $context = $this->context(
            authorizedOrganization: true
        );

        $result = $this->repository()->prepare(
            $this->command(
                $context['employee'],
                $context['operator']
            ),
            'vanguard_normalized',
            'vanguard-normalization-v1',
        );

        self::assertSame(
            $context['photo']->id,
            $result->photoId
        );

        self::assertSame(
            $context['operator']->name,
            $result->requesterName
        );

        self::assertSame(
            FacialPhotoDerivativeStatus::Failed,
            $result->previousStatus
        );
    }

    public function test_employee_reprocessing_reauthorizes_the_unit_in_the_backend(): void
    {
        $context = $this->context(
            authorizedOrganization: false
        );

        $this->assertFailure(
            'operation_not_authorized',
            fn () => $this->repository()->prepare(
                $this->command(
                    $context['employee'],
                    $context['operator']
                ),
                'vanguard_normalized',
                'vanguard-normalization-v1',
            )
        );
    }

    public function test_missing_employee_fails_with_subject_specific_failure(): void
    {
        $operator = User::factory()->create([
            'name' => 'OPERADOR E1-A3 AUSENTE',
        ]);

        $this->assertFailure(
            'employee_not_found',
            fn () => $this->repository()->prepare(
                new ReprocessFacialPhotoDerivativeCommand(
                    subjectType: FacialPhotoSubjectType::Employee,
                    subjectId: (string) Str::uuid(),
                    operatorUserId: (int) $operator->id,
                    requestId: (string) Str::uuid(),
                ),
                'vanguard_normalized',
                'vanguard-normalization-v1',
            )
        );
    }

    /**
     * @return array{
     *     employee: EmployeeRecord,
     *     operator: User,
     *     photo: FacialPhotoRecord
     * }
     */
    private function context(
        bool $authorizedOrganization
    ): array {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO E1-A3',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE E1-A3 LTDA',
                'display_name' => 'UNIDADE E1-A3',
                'unit_code' => 'E1-A3',
            ]);

        $employee = EmployeeRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'employee_code' => 'EMP-E1-A3',
            'full_name' => 'FUNCIONÁRIO E1-A3',
            'employment_type' => 'employee',
            'status' => 'active',
        ]);

        $operator = User::factory()->create([
            'name' => 'OPERADOR E1-A3',
        ]);

        Permission::findOrCreate(
            'ReprocessFacialPhotoDerivative:EmployeeRecord',
            'web'
        );

        $role = Role::findOrCreate(
            'employee_reprocess_backend_test',
            'web'
        );

        $role->syncPermissions([
            'ReprocessFacialPhotoDerivative:EmployeeRecord',
        ]);

        $operator->assignRole(
            $role
        );

        $operator->tenants()->attach(
            $tenant->id,
            [
                'role' => 'manager',
                'is_owner' => false,
                'is_active' => true,
                'joined_at' => now(),
            ]
        );

        if ($authorizedOrganization) {
            $operator->organizations()->attach(
                $organization->id,
                [
                    'role' => 'manager',
                    'is_active' => true,
                    'granted_at' => now(),
                ]
            );
        }

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $photo = $employee
            ->facialPhotos()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'created_by' => $operator->id,
                'source' => FacialPhotoSource::Webcam,
                'status' => FacialPhotoStatus::Approved,
                'captured_at' => now(),
                'approved_at' => now(),
                'sha256' => str_repeat('a', 64),
            ]);

        FacialPhotoDerivativeRecord::query()->create([
            'facial_photo_id' => $photo->id,
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'profile' => 'vanguard_normalized',
            'policy_version' => 'vanguard-normalization-v1',
            'status' => FacialPhotoDerivativeStatus::Failed,
            'source_sha256' => str_repeat('a', 64),
            'media_id' => null,
            'sha256' => null,
            'generated_at' => null,
        ]);

        return [
            'employee' => $employee,
            'operator' => $operator,
            'photo' => $photo,
        ];
    }

    private function command(
        EmployeeRecord $employee,
        User $operator
    ): ReprocessFacialPhotoDerivativeCommand {
        return new ReprocessFacialPhotoDerivativeCommand(
            subjectType: FacialPhotoSubjectType::Employee,
            subjectId: (string) $employee->id,
            operatorUserId: (int) $operator->id,
            requestId: (string) Str::uuid(),
        );
    }

    private function repository(): ReprocessFacialPhotoDerivativeRepository
    {
        return app(
            ReprocessFacialPhotoDerivativeRepository::class
        );
    }

    private function assertFailure(
        string $failureCode,
        callable $operation
    ): void {
        try {
            $operation();

            $this->fail(
                'A operação deveria ter sido bloqueada.'
            );
        } catch (
            ReprocessFacialPhotoDerivativeException $exception
        ) {
            self::assertSame(
                $failureCode,
                $exception->failureCode
            );
        }
    }
}
