<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleTemplateRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeWorkScheduleTemplateRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_relates_template_to_tenant_days_and_employee_schedule(): void
    {
        $tenant = TenantRecord::query()->create([
            'name' => 'AGRONORTE',
            'status' => 'active',
        ]);

        $template = EmployeeWorkScheduleTemplateRecord::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'Administrativo 44h',
            'name' => 'Administrativo 44h',
            'type' => 'standard',
            'description' => '08:00 às 12:00 - 13:00 às 17:48 - SAB DOM DSR',
            'weekly_workload_minutes' => 2640,
            'daily_workload_minutes' => 528,
            'tolerance_before_start_minutes' => 30,
            'status' => 'active',
            'is_system' => true,
        ]);

        $template->days()->create([
            'weekday' => 1,
            'sequence' => 1,
            'is_working_day' => true,
            'work_starts_at' => '08:00',
            'work_ends_at' => '17:48',
            'break_starts_at' => '12:00',
            'break_ends_at' => '13:00',
        ]);

        $employee = EmployeeRecord::query()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'Funcionário Demo',
            'employment_type' => 'employee',
            'status' => 'active',
        ]);

        EmployeeWorkScheduleRecord::query()->create([
            'employee_id' => $employee->id,
            'employee_work_schedule_template_id' => $template->id,
            'name' => $template->name,
            'type' => $template->type,
            'weekly_workload_minutes' => $template->weekly_workload_minutes,
            'daily_workload_minutes' => $template->daily_workload_minutes,
            'tolerance_before_start_minutes' => $template->tolerance_before_start_minutes,
            'valid_from' => now()->toDateString(),
            'is_active' => true,
        ]);

        $loaded = EmployeeWorkScheduleTemplateRecord::query()
            ->with(['tenant', 'days', 'employeeWorkSchedules'])
            ->findOrFail($template->id);

        $this->assertSame($tenant->id, $loaded->tenant->id);
        $this->assertSame('administrativo_44h', $loaded->code);
        $this->assertSame('Padrão', $loaded->type_display);
        $this->assertSame('Ativa', $loaded->status_display);
        $this->assertSame('08:00 às 12:00 - 13:00 às 17:48 - SAB DOM DSR', $loaded->schedule_display);
        $this->assertCount(1, $loaded->days);
        $this->assertCount(1, $loaded->employeeWorkSchedules);
    }
}
