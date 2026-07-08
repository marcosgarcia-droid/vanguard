<?php

namespace Tests\Unit\Modules\Identity\UI\Filament\Resources\EmployeeWorkScheduleTemplateRecords;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleTemplateRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Identity\UI\Filament\Resources\EmployeeWorkScheduleTemplateRecords\Schemas\EmployeeWorkScheduleTemplateRecordForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeWorkScheduleTemplateRecordFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_converts_hours_and_minutes_to_database_minutes(): void
    {
        $data = EmployeeWorkScheduleTemplateRecordForm::normalizeData([
            'name' => 'Administrativo 44h',
            'weekly_workload_hours' => 44,
            'weekly_workload_remaining_minutes' => 0,
            'daily_workload_hours' => 8,
            'daily_workload_remaining_minutes' => 48,
            'weekly_rule_groups' => [],
        ]);

        $this->assertSame(2640, $data['weekly_workload_minutes']);
        $this->assertSame(528, $data['daily_workload_minutes']);
        $this->assertSame(0, $data['tolerance_after_end_minutes']);
        $this->assertArrayNotHasKey('weekly_rule_groups', $data);
    }

    public function test_it_generates_days_from_grouped_weekly_rules(): void
    {
        $tenant = TenantRecord::query()->create([
            'name' => 'AGRONORTE',
            'status' => 'active',
        ]);

        $template = EmployeeWorkScheduleTemplateRecord::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'administrativo_44h',
            'name' => 'Administrativo 44h',
            'type' => 'standard',
            'description' => '08:00 às 12:00 - 13:00 às 17:48 - SAB DOM DSR',
            'status' => 'active',
        ]);

        EmployeeWorkScheduleTemplateRecordForm::syncGeneratedDays($template, [
            [
                'weekday_from' => 1,
                'weekday_to' => 5,
                'is_working_day' => true,
                'work_starts_at' => '08:00',
                'break_starts_at' => '12:00',
                'break_ends_at' => '13:00',
                'work_ends_at' => '17:48',
                'ends_next_day' => false,
            ],
            [
                'weekday_from' => 6,
                'weekday_to' => 7,
                'is_working_day' => false,
                'notes' => 'DSR',
            ],
        ]);

        $template->refresh();

        $this->assertCount(7, $template->days);
        $this->assertCount(5, $template->days->where('is_working_day', true));
        $this->assertCount(2, $template->days->where('is_working_day', false));
        $this->assertSame('08:00', $template->days->firstWhere('weekday', 1)->work_starts_at);
        $this->assertSame('17:48', $template->days->firstWhere('weekday', 5)->work_ends_at);
        $this->assertSame('DSR', $template->days->firstWhere('weekday', 6)->notes);
    }
}
