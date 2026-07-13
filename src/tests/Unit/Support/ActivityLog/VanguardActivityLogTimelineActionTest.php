<?php

namespace Tests\Unit\Support\ActivityLog;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeContactRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Support\ActivityLog\VanguardActivityLogTimelineAction;
use Database\Seeders\VanguardEmployeeDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class VanguardActivityLogTimelineActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_audits_employee_contact_changes_under_employee_history_without_exposing_contact_value(): void
    {
        $this->seed(VanguardEmployeeDemoSeeder::class);

        $employee = EmployeeRecord::query()->firstOrFail();

        $contact = EmployeeContactRecord::query()->create([
            'employee_id' => $employee->id,
            'type' => 'email',
            'label' => 'Teste automatizado histórico filho',
            'value' => 'teste.automatizado@vanguard.local',
            'is_primary' => false,
            'notes' => 'Contato temporário criado pelo teste automatizado.',
        ]);

        $contact->update([
            'label' => 'Teste automatizado histórico filho atualizado',
            'notes' => 'Contato temporário atualizado pelo teste automatizado.',
        ]);

        $activities = Activity::query()
            ->where('subject_type', EmployeeContactRecord::class)
            ->where('subject_id', (string) $contact->getKey())
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $activities);
        $this->assertSame(['created', 'updated'], $activities->pluck('event')->all());

        foreach ($activities as $activity) {
            $this->assertSame(EmployeeRecord::class, data_get($activity->properties, 'vanguard_parent_type'));
            $this->assertSame((string) $employee->id, data_get($activity->properties, 'vanguard_parent_id'));
            $this->assertSame('Funcionário', data_get($activity->properties, 'vanguard_parent_label'));

            $changes = $activity->attribute_changes?->toArray() ?? [];

            foreach (['attributes', 'old'] as $section) {
                $values = $changes[$section] ?? [];

                $this->assertArrayNotHasKey('value', $values);
                $this->assertArrayNotHasKey('normalized_value', $values);
            }
        }

        $action = VanguardActivityLogTimelineAction::make();

        $method = new ReflectionMethod($action, 'getActivities');
        $method->setAccessible(true);

        $timelineActivities = $method->invoke($action, $employee);
        $timelineActivityIds = $timelineActivities->pluck('id')->all();

        $this->assertContains($activities->first()->id, $timelineActivityIds);
        $this->assertContains($activities->last()->id, $timelineActivityIds);
    }
}
