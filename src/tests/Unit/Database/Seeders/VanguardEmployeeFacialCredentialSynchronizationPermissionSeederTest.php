<?php

declare(strict_types=1);

namespace Tests\Unit\Database\Seeders;

use Tests\TestCase;

final class VanguardEmployeeFacialCredentialSynchronizationPermissionSeederTest extends TestCase
{
    public function test_employee_sync_creation_permission_is_seeded_without_execution_permission(): void
    {
        $source = file_get_contents(
            database_path(
                'seeders/VanguardAccessSeeder.php'
            )
        );

        $this->assertIsString($source);

        $this->assertSame(
            2,
            substr_count(
                $source,
                'CreateFacialCredentialSynchronization:EmployeeRecord'
            )
        );

        $this->assertStringNotContainsString(
            'ExecuteFacialCredentialSynchronization:EmployeeRecord',
            $source
        );
    }
}
