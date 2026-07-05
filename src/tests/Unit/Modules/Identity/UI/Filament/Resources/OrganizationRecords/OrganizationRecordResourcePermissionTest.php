<?php

namespace Tests\Unit\Modules\Identity\UI\Filament\Resources\OrganizationRecords;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\OrganizationRecordResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganizationRecordResourcePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_can_only_view_organizations(): void
    {
        $user = $this->userWithPermissions([
            'ViewAny:OrganizationRecord',
            'View:OrganizationRecord',
        ]);

        $this->actingAs($user);

        $record = $this->organization();

        $this->assertTrue(OrganizationRecordResource::canViewAny());
        $this->assertTrue(OrganizationRecordResource::canView($record));
        $this->assertFalse(OrganizationRecordResource::canCreate());
        $this->assertFalse(OrganizationRecordResource::canEdit($record));
        $this->assertFalse(OrganizationRecordResource::canDelete($record));
    }

    public function test_operator_can_create_and_update_but_cannot_delete(): void
    {
        $user = $this->userWithPermissions([
            'ViewAny:OrganizationRecord',
            'View:OrganizationRecord',
            'Create:OrganizationRecord',
            'Update:OrganizationRecord',
        ]);

        $this->actingAs($user);

        $record = $this->organization();

        $this->assertTrue(OrganizationRecordResource::canCreate());
        $this->assertTrue(OrganizationRecordResource::canEdit($record));
        $this->assertFalse(OrganizationRecordResource::canDelete($record));
        $this->assertFalse(OrganizationRecordResource::canForceDelete($record));
    }

    public function test_super_admin_can_manage_everything(): void
    {
        $user = User::factory()->create();

        Role::findOrCreate('super_admin', 'web');
        $user->assignRole('super_admin');

        $this->actingAs($user);

        $record = $this->organization();

        $this->assertTrue(OrganizationRecordResource::canViewAny());
        $this->assertTrue(OrganizationRecordResource::canView($record));
        $this->assertTrue(OrganizationRecordResource::canCreate());
        $this->assertTrue(OrganizationRecordResource::canEdit($record));
        $this->assertTrue(OrganizationRecordResource::canDelete($record));
        $this->assertTrue(OrganizationRecordResource::canForceDelete($record));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $role = Role::findOrCreate('test_role_'.Str::random(8), 'web');

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function organization(): OrganizationRecord
    {
        return OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'status' => 'active',
            'legal_name' => 'AGRONORTE NUTRICAO ANIMAL LTDA',
        ]);
    }
}
