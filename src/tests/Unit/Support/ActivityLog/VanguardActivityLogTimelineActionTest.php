<?php

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeContactRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Support\ActivityLog\VanguardActivityLogTimelineAction;
use Database\Seeders\VanguardEmployeeDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('audits employee contact changes under the employee history without exposing contact value', function (): void {
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

    expect($activities)->toHaveCount(2)
        ->and($activities->pluck('event')->all())->toBe([
            'created',
            'updated',
        ]);

    $activities->each(function (Activity $activity) use ($employee): void {
        $properties = $activity->properties?->toArray() ?? [];

        expect($properties)->toMatchArray([
            'vanguard_parent_type' => EmployeeRecord::class,
            'vanguard_parent_id' => (string) $employee->id,
            'vanguard_parent_label' => 'Funcionário',
        ]);

        $changes = $activity->attribute_changes?->toArray() ?? [];

        foreach (['attributes', 'old'] as $section) {
            $values = $changes[$section] ?? [];

            expect(array_key_exists('value', $values))->toBeFalse()
                ->and(array_key_exists('normalized_value', $values))->toBeFalse();
        }
    });

    $action = VanguardActivityLogTimelineAction::make();

    $method = new ReflectionMethod($action, 'getActivities');
    $method->setAccessible(true);

    $timelineActivities = $method->invoke($action, $employee);
    $timelineActivityIds = $timelineActivities->pluck('id')->all();

    expect($timelineActivityIds)->toContain($activities->first()->id);
    expect($timelineActivityIds)->toContain($activities->last()->id);
});
