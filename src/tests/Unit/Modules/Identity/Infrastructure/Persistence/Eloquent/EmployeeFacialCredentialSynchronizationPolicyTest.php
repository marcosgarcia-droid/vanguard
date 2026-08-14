<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecordPolicy;
use Tests\TestCase;

final class EmployeeFacialCredentialSynchronizationPolicyTest extends TestCase
{
    public function test_policy_uses_a_dedicated_creation_permission(): void
    {
        $source = file_get_contents(
            app_path(
                'Modules/Identity/Infrastructure/Persistence/Eloquent/'
                .'EmployeeRecordPolicy.php'
            )
        );

        $this->assertIsString($source);

        $this->assertStringContainsString(
            'createFacialCredentialSynchronization(',
            $source
        );

        $this->assertStringContainsString(
            'CreateFacialCredentialSynchronization:EmployeeRecord',
            $source
        );

        $this->assertStringContainsString(
            'belongsToActiveUserTenant(',
            $source
        );

        $this->assertStringNotContainsString(
            'ExecuteFacialCredentialSynchronization:EmployeeRecord',
            $source
        );
    }

    public function test_generic_employee_update_does_not_grant_sync_creation(): void
    {
        $policy = new EmployeeRecordPolicy;

        $user = $this->createMock(User::class);
        $employee = new EmployeeRecord;

        $user
            ->expects($this->once())
            ->method('can')
            ->with(
                'CreateFacialCredentialSynchronization:EmployeeRecord'
            )
            ->willReturn(false);

        $this->assertFalse(
            $policy->createFacialCredentialSynchronization(
                $user,
                $employee
            )
        );
    }
}
