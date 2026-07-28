<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Pages\ListVisitorRecords;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class VisitorRecordFacialPhotoFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        app(TenantContext::class)
            ->clearSelectedTenant();
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)
            ->clearSelectedTenant();

        parent::tearDown();
    }

    public function test_it_filters_by_latest_facial_photo_status_and_missing_photo(): void
    {
        $tenant = TenantRecord::query()
            ->create([
                'id' => (string) Str::uuid(),
                'name' => 'GRUPO FILTRO FACIAL',
                'status' => 'active',
            ]);

        $organization =
            OrganizationRecord::query()
                ->create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'status' => 'active',
                    'legal_name' => 'UNIDADE FILTRO FACIAL LTDA',
                    'display_name' => 'UNIDADE FILTRO FACIAL',
                    'unit_code' => 'FFF-01',
                ]);

        $operator = $this->operator();

        $operator->organizations()->attach(
            $organization->id,
            [
                'role' => 'operator',
                'is_active' => true,
                'granted_at' => now(),
            ]
        );

        $this->actingAs(
            $operator
        );

        app(TenantContext::class)
            ->initializeForUser(
                $operator
            );

        $withoutPhoto = $this->visitor(
            $tenant,
            $organization,
            'VISITANTE SEM FOTO FACIAL'
        );

        $pending = $this->visitor(
            $tenant,
            $organization,
            'VISITANTE FOTO PENDENTE'
        );

        $approved = $this->visitor(
            $tenant,
            $organization,
            'VISITANTE FOTO APROVADA'
        );

        $rejectedLatest = $this->visitor(
            $tenant,
            $organization,
            'VISITANTE FOTO REPROVADA'
        );

        $outdated = $this->visitor(
            $tenant,
            $organization,
            'VISITANTE FOTO DESATUALIZADA'
        );

        $this->attachPhoto(
            visitor: $pending,
            operator: $operator,
            status: FacialPhotoStatus::PendingValidation,
            capturedAt: now()->subMinutes(5),
        );

        $this->attachPhoto(
            visitor: $approved,
            operator: $operator,
            status: FacialPhotoStatus::Approved,
            capturedAt: now()->subMinutes(4),
        );

        /*
         * Confirma que o filtro considera a foto mais recente,
         * não qualquer foto histórica do visitante.
         */
        $this->attachPhoto(
            visitor: $rejectedLatest,
            operator: $operator,
            status: FacialPhotoStatus::Approved,
            capturedAt: now()->subMinutes(3),
        );

        $this->attachPhoto(
            visitor: $rejectedLatest,
            operator: $operator,
            status: FacialPhotoStatus::Rejected,
            capturedAt: now()->subMinutes(2),
        );

        $this->attachPhoto(
            visitor: $outdated,
            operator: $operator,
            status: FacialPhotoStatus::Outdated,
            capturedAt: now()->subMinute(),
        );

        $allVisitors = [
            $withoutPhoto,
            $pending,
            $approved,
            $rejectedLatest,
            $outdated,
        ];

        $this->assertFilterResult(
            value: 'not_registered',
            visible: [
                $withoutPhoto,
            ],
            hidden: [
                $pending,
                $approved,
                $rejectedLatest,
                $outdated,
            ],
        );

        $this->assertFilterResult(
            value: FacialPhotoStatus::PendingValidation->value,
            visible: [
                $pending,
            ],
            hidden: [
                $withoutPhoto,
                $approved,
                $rejectedLatest,
                $outdated,
            ],
        );

        $this->assertFilterResult(
            value: FacialPhotoStatus::Approved->value,
            visible: [
                $approved,
            ],
            hidden: [
                $withoutPhoto,
                $pending,
                $rejectedLatest,
                $outdated,
            ],
        );

        $this->assertFilterResult(
            value: FacialPhotoStatus::Rejected->value,
            visible: [
                $rejectedLatest,
            ],
            hidden: [
                $withoutPhoto,
                $pending,
                $approved,
                $outdated,
            ],
        );

        $this->assertFilterResult(
            value: FacialPhotoStatus::Outdated->value,
            visible: [
                $outdated,
            ],
            hidden: [
                $withoutPhoto,
                $pending,
                $approved,
                $rejectedLatest,
            ],
        );

        /*
         * Um valor adulterado não pode remover silenciosamente
         * a restrição e retornar todos os visitantes.
         */
        Livewire::test(
            ListVisitorRecords::class
        )
            ->assertTableFilterExists(
                'facial_photo_status'
            )
            ->filterTable(
                'facial_photo_status',
                'status_desconhecido'
            )
            ->assertCanNotSeeTableRecords(
                $allVisitors
            );
    }

    /**
     * @param  list<VisitorRecord>  $visible
     * @param  list<VisitorRecord>  $hidden
     */
    private function assertFilterResult(
        string $value,
        array $visible,
        array $hidden
    ): void {
        Livewire::test(
            ListVisitorRecords::class
        )
            ->assertTableFilterExists(
                'facial_photo_status'
            )
            ->filterTable(
                'facial_photo_status',
                $value
            )
            ->assertCanSeeTableRecords(
                $visible
            )
            ->assertCanNotSeeTableRecords(
                $hidden
            );
    }

    private function visitor(
        TenantRecord $tenant,
        OrganizationRecord $organization,
        string $name
    ): VisitorRecord {
        return VisitorRecord::query()
            ->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'visitor_code' => 'VIS-'.Str::upper(
                    Str::random(10)
                ),
                'full_name' => $name,
                'preferred_name' => null,
                'status' => VisitorStatus::Active,
            ]);
    }

    private function attachPhoto(
        VisitorRecord $visitor,
        User $operator,
        FacialPhotoStatus $status,
        CarbonInterface $capturedAt
    ): void {
        $visitor->facialPhotos()
            ->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $visitor->tenant_id,
                'organization_id' => $visitor->organization_id,
                'created_by' => $operator->id,
                'source' => FacialPhotoSource::Webcam,
                'status' => $status,
                'captured_at' => $capturedAt,
                'analyzed_at' => $capturedAt,
                'approved_at' => $status === FacialPhotoStatus::Approved
                        ? $capturedAt
                        : null,
                'rejected_at' => $status === FacialPhotoStatus::Rejected
                        ? $capturedAt
                        : null,
                'outdated_at' => $status === FacialPhotoStatus::Outdated
                        ? $capturedAt
                        : null,
                'width' => 640,
                'height' => 640,
                'mime_type' => 'image/jpeg',
                'size_bytes' => 1024,
                'sha256' => hash(
                    'sha256',
                    implode(
                        '|',
                        [
                            $visitor->id,
                            $status->value,
                            $capturedAt->toISOString(),
                        ]
                    )
                ),
                'validation_version' => 'facial-filter-test-v1',
                'validation_result' => [],
                'rejection_reasons' => [],
            ]);
    }

    private function operator(): User
    {
        $permissions = [
            'ViewAny:VisitorRecord',
            'View:VisitorRecord',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate(
                $permission,
                'web'
            );
        }

        $role = Role::findOrCreate(
            'visitor_facial_filter_operator_test',
            'web'
        );

        $role->syncPermissions(
            $permissions
        );

        $user = User::factory()
            ->create();

        $user->assignRole(
            $role
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        return $user;
    }
}
