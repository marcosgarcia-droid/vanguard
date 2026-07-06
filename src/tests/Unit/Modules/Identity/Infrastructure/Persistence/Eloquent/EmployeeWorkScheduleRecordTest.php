<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleDayRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeWorkScheduleRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_relates_employee_work_schedule_and_days(): void
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'AGRONORTE',
            'status' => 'active',
        ]);

        $employee = EmployeeRecord::query()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'João Silva',
            'status' => 'active',
            'employment_type' => 'employee',
        ]);

        $schedule = EmployeeWorkScheduleRecord::query()->create([
            'employee_id' => $employee->id,
            'name' => 'Jornada administrativa',
            'type' => 'fixed',
            'weekly_workload_minutes' => 2640,
            'daily_workload_minutes' => 528,
            'tolerance_before_start_minutes' => 30,
            'tolerance_after_end_minutes' => 0,
            'is_active' => true,
        ]);

        EmployeeWorkScheduleDayRecord::query()->create([
            'employee_work_schedule_id' => $schedule->id,
            'weekday' => 1,
            'sequence' => 1,
            'is_working_day' => true,
            'work_starts_at' => '08:00:00',
            'work_ends_at' => '18:00:00',
            'break_starts_at' => '12:00:00',
            'break_ends_at' => '13:12:00',
        ]);

        $employee->refresh();

        $this->assertTrue($schedule->employee->is($employee));
        $this->assertSame(1, $schedule->days()->count());
        $this->assertSame(2640, $employee->currentWorkSchedule()?->weekly_workload_minutes);
        $this->assertSame(30, $employee->currentWorkSchedule()?->tolerance_before_start_minutes);
    }
}
