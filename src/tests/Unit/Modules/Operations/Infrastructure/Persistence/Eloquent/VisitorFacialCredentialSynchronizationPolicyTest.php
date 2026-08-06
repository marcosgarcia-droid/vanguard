<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecordPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class VisitorFacialCredentialSynchronizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSION =
        'CreateFacialCredentialSynchronization:VisitorRecord';

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantContext::class)
            ->clearSelectedTenant();

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        Permission::findOrCreate(
            self::PERMISSION,
            'web'
        );
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)
            ->clearSelectedTenant();

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_it_allows_an_authorized_user_in_the_selected_group_and_unit(): void
    {
        [
            $tenant,
            $organization,
            $visitor,
        ] = $this->scopeFixture('ALLOWED');

        $user = User::factory()->create();

        $this->grantTenantAccess(
            user: $user,
            tenant: $tenant,
        );

        $this->grantOrganizationAccess(
            user: $user,
            organization: $organization,
        );

        $user->givePermissionTo(
            self::PERMISSION
        );

        app(TenantContext::class)
            ->initializeForUser($user);

        $policy = app(
            VisitorRecordPolicy::class
        );

        self::assertTrue(
            $policy->createFacialCredentialSynchronization(
                $user,
                $visitor
            )
        );

        self::assertTrue(
            Gate::forUser($user)->allows(
                'createFacialCredentialSynchronization',
                $visitor
            )
        );
    }

    public function test_it_denies_a_user_without_the_specific_permission(): void
    {
        [
            $tenant,
            $organization,
            $visitor,
        ] = $this->scopeFixture(' $policy->createFacialCredentialSynchronization(
                $user,
                $visitor
            )
        );

        self::assertTrue(
            Gate::forUserNO-PERMISSION');

        $user = User::factory()->create();

        $this->grantTenantAccess(
            user: $user,
            tenant: $tenant,
        );

        $this->grantOrganizationAccess(
            user: $user,
            organization: $organization,
        );

        app(TenantContext::class)
            ->initializeForUser($user);

        self::assertFalse(
            app(VisitorRecordPolicy::class)
                ->createFacialCredentialSynchronization(
                    $user,
                    $visitor
                )
        );

        self::assertFalse(
            Gate::forUser($user)->allows(
                'createFacialCredentialSynchronization',
                $visitor
            )
        );
    }

    public function test_it_denies_a_user_without_access_to_the_visitors_unit(): void
    {
        [
            $tenant,
            ,
            $visitor,
        ] = $this->scopeFixture('NO-UNIT');

        $user = User::factory()->create();

        $this->grantTenantAccess(
            user: $user,
            tenant: $tenant,
        );

        $user->givePermissionTo(
            self::PERMISSION
        );

        app(TenantContext::class)
            ->initializeForUser($user);

        self::assertFalse(
            app(VisitorRecordPolicy::class)
                ->createFacialCredentialSynchronization(
                    $user,
                    $visitor
                )
        );
    }

    public function test_it_denies_when_another_group_is_selected(): void
    {
        [
            $visitorTenant,
            $visitorOrganization,
            $visitor,
        ] = $this->scopeFixture('VISITOR-GROUP');

        [
            $selectedTenant,
            $selectedOrganization,
        ] = $this->scopeFixture(
            'SELECTED-GROUP'
        );

        $user = User::factory()->create();

        foreach (
            [
                $visitorTenant,
                $selectedTenant,
            ] as $tenant
        ) {
            $this->grantTenantAccess(
                user: $user,
                tenant: $tenant,
            );
        }

        foreach (
            [
                $visitorOrganization,
                $selectedOrganization,
            ] as $organization
        ) {
            $this->grantOrganizationAccess(
                user: $user,
                organization: $organization,
            );
        }

        $user->givePermissionTo(
            self::PERMISSION
        );

        $selected = app(
            TenantContext::class
        )->selectTenantForUser(
            $user,
            $selectedTenant
        );

        self::assertTrue($selected);

        self::assertFalse(
            app(VisitorRecordPolicy::class)
                ->createFacialCredentialSynchronization(
                    $user,
                    $visitor
                )
        );

        self::assertFalse(
            Gate::forUser($user)->allows(
                'createFacialCredentialSynchronization',
                $visitor
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
    private function scopeFixture(
        string $suffix
    ): array {
        $tenant =
            TenantRecord::query()->create([
                'id' => (string) Str::uuid(),
                'name' => 'GRUPO SINTÉTICO '.$suffix,
                'status' => 'active',
            ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE SINTÉTICA '.$suffix.' LTDA',
                'display_name' => 'UNIDADE SINTÉTICA '.$suffix,
            ]);

        $visitor =
            VisitorRecord::query()->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'visitor_code' => 'VIS-'.Str::upper(
                    Str::random(10)
                ),
                'full_name' => 'VISITANTE SINTÉTICO '.$suffix,
                'status' => 'active',
            ]);

        return [
            $tenant,
            $organization,
            $visitor,
        ];
    }

    private function grantTenantAccess(
        User $user,
        TenantRecord $tenant,
    ): void {
        $tenant->users()->syncWithoutDetaching([
            $user->id => [
                'role' => 'operator',
                'is_owner' => false,
                'is_active' => true,
                'joined_at' => now(),
            ],
        ]);
    }

    private function grantOrganizationAccess(
        User $user,
        OrganizationRecord $organization,
    ): void {
        $user->organizations()
            ->syncWithoutDetaching([
                $organization->id => [
                    'is_active' => true,
                    'granted_at' => now(),
                ],
            ]);
    }
}
