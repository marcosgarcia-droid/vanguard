<?php

namespace Tests\Unit\Database\Seeders;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use Database\Seeders\VanguardEmployeeDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VanguardEmployeeDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_synthetic_employees_with_unformatted_documents_contacts_and_addresses(): void
    {
        $this->seed(VanguardEmployeeDemoSeeder::class);

        $employees = EmployeeRecord::query()
            ->with(['documents', 'contacts', 'addresses', 'workSchedules.days'])
            ->where('employee_code', 'like', 'DEMO-%')
            ->get();

        $this->assertCount(50, $employees);

        $employee = $employees->firstOrFail();

        $this->assertMatchesRegularExpression('/^DEMO-\d{4}$/', (string) $employee->employee_code);
        $this->assertMatchesRegularExpression('/^\d{11}$/', (string) $employee->cpf);
        $this->assertMatchesRegularExpression('/^\d{11}$/', (string) $employee->mobile_phone);
        $this->assertMatchesRegularExpression('/^\d{8}$/', (string) $employee->primaryAddress()?->postal_code);

        $schedule = $employee->currentWorkSchedule();

        $this->assertNotNull($schedule);
        $this->assertSame(2640, $schedule->weekly_workload_minutes);
        $this->assertSame(528, $schedule->daily_workload_minutes);
        $this->assertSame(30, $schedule->tolerance_before_start_minutes);
        $this->assertSame(5, $schedule->days()->count());
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(VanguardEmployeeDemoSeeder::class);
        $this->seed(VanguardEmployeeDemoSeeder::class);

        $this->assertSame(
            50,
            EmployeeRecord::query()
                ->where('employee_code', 'like', 'DEMO-%')
                ->count(),
        );
    }
}
