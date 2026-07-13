<?php

namespace Tests\Unit\Support\ActivityLog;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorContactRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorDocumentRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Support\ActivityLog\VanguardActivityLogTimelineAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionMethod;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class VisitorActivityLogTimelineActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_audits_visitor_children_without_exposing_sensitive_data(): void
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO DEMONSTRAÇÃO',
            'status' => 'active',
        ]);

        $organization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'UNIDADE DEMONSTRAÇÃO LTDA',
            'display_name' => 'UNIDADE DEMONSTRAÇÃO',
            'unit_code' => 'DEM-01',
        ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_code' => 'VIS-001',
            'full_name' => 'Visitante Demonstração',
            'birth_date' => '1990-01-15',
            'status' => VisitorStatus::Active,
        ]);

        $document = VisitorDocumentRecord::query()->create([
            'visitor_id' => $visitor->id,
            'type' => 'cpf',
            'number' => '123.456.789-09',
            'is_primary' => true,
        ]);

        $contact = VisitorContactRecord::query()->create([
            'visitor_id' => $visitor->id,
            'type' => 'mobile',
            'label' => 'Celular principal',
            'value' => '(38) 99999-0000',
            'is_primary' => true,
        ]);

        $visit = VisitRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'status' => VisitStatus::Scheduled,
            'purpose' => 'Visita técnica',
            'expected_start_at' => now()->addHour(),
            'expected_end_at' => now()->addHours(2),
        ]);

        $visitorActivity = Activity::query()
            ->where('subject_type', VisitorRecord::class)
            ->where('subject_id', (string) $visitor->getKey())
            ->where('event', 'created')
            ->firstOrFail();

        $this->assertSensitiveAttributesAreAbsent(
            $visitorActivity,
            [
                'birth_date',
                'photo_path',
                'photo_disk',
                'photo_uploaded_at',
            ]
        );

        $documentActivity = Activity::query()
            ->where('subject_type', VisitorDocumentRecord::class)
            ->where('subject_id', (string) $document->getKey())
            ->where('event', 'created')
            ->firstOrFail();

        $this->assertVisitorParentReference(
            $documentActivity,
            $visitor
        );

        $this->assertSensitiveAttributesAreAbsent(
            $documentActivity,
            [
                'number',
                'normalized_number',
            ]
        );

        $contactActivity = Activity::query()
            ->where('subject_type', VisitorContactRecord::class)
            ->where('subject_id', (string) $contact->getKey())
            ->where('event', 'created')
            ->firstOrFail();

        $this->assertVisitorParentReference(
            $contactActivity,
            $visitor
        );

        $this->assertSensitiveAttributesAreAbsent(
            $contactActivity,
            [
                'value',
                'normalized_value',
            ]
        );

        $visitActivity = Activity::query()
            ->where('subject_type', VisitRecord::class)
            ->where('subject_id', (string) $visit->getKey())
            ->where('event', 'created')
            ->firstOrFail();

        $this->assertVisitorParentReference(
            $visitActivity,
            $visitor
        );

        $action = VanguardActivityLogTimelineAction::make();

        $method = new ReflectionMethod(
            $action,
            'getActivities'
        );

        $method->setAccessible(true);

        $timelineActivities = $method->invoke(
            $action,
            $visitor
        );

        $timelineActivityIds = $timelineActivities
            ->pluck('id')
            ->all();

        $this->assertContains(
            $visitorActivity->id,
            $timelineActivityIds
        );

        $this->assertContains(
            $documentActivity->id,
            $timelineActivityIds
        );

        $this->assertContains(
            $contactActivity->id,
            $timelineActivityIds
        );

        $this->assertContains(
            $visitActivity->id,
            $timelineActivityIds
        );
    }

    private function assertVisitorParentReference(
        Activity $activity,
        VisitorRecord $visitor
    ): void {
        $this->assertSame(
            VisitorRecord::class,
            data_get(
                $activity->properties,
                'vanguard_parent_type'
            )
        );

        $this->assertSame(
            (string) $visitor->id,
            data_get(
                $activity->properties,
                'vanguard_parent_id'
            )
        );

        $this->assertSame(
            'Visitante',
            data_get(
                $activity->properties,
                'vanguard_parent_label'
            )
        );
    }

    /**
     * @param  array<int, string>  $attributes
     */
    private function assertSensitiveAttributesAreAbsent(
        Activity $activity,
        array $attributes
    ): void {
        $changes = $activity->attribute_changes?->toArray() ?? [];

        foreach (['attributes', 'old'] as $section) {
            $values = $changes[$section] ?? [];

            foreach ($attributes as $attribute) {
                $this->assertArrayNotHasKey(
                    $attribute,
                    $values
                );
            }
        }
    }
}
