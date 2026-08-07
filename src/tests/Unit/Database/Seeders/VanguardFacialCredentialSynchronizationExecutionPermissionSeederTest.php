<?php

declare(strict_types=1);

namespace Tests\Unit\Database\Seeders;

use Database\Seeders\VanguardAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class VanguardFacialCredentialSynchronizationExecutionPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSION =
        'ExecuteFacialCredentialSynchronization:VisitorRecord';

    public function test_it_assigns_the_permission_only_to_the_authorized_roles(): void
    {
        $this->seed(
            VanguardAccessSeeder::class
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        self::assertSame(
            1,
            Permission::query()
                ->where(
                    'name',
                    self::PERMISSION
                )
                ->where(
                    'guard_name',
                    'web'
                )
                ->count()
        );

        foreach (
            [
                'super_admin',
                'admin',
                'manager',
                'operator',
            ] as $roleName
        ) {
            self::assertTrue(
                Role::findByName(
                    $roleName,
                    'web'
                )->hasPermissionTo(
                    self::PERMISSION
                ),
                sprintf(
                    'O papel %s deveria possuir a permissão.',
                    $roleName
                )
            );
        }

        foreach (
            [
                'viewer',
                'panel_user',
            ] as $roleName
        ) {
            self::assertFalse(
                Role::findByName(
                    $roleName,
                    'web'
                )->hasPermissionTo(
                    self::PERMISSION
                ),
                sprintf(
                    'O papel %s não deveria possuir a permissão.',
                    $roleName
                )
            );
        }
    }
}
