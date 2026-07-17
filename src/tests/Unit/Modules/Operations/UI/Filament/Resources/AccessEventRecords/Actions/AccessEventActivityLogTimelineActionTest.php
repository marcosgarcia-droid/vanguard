<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionSource;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalExecutionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions\AccessEventActivityLogTimelineAction;
use App\Support\ActivityLog\VanguardActivityLogPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionMethod;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AccessEventActivityLogTimelineActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_collects_event_decision_execution_and_manual_reprocessing_activities(): void
    {
        $context = $this->createContext();

        $decisionActivity = Activity::query()
            ->where(
                'subject_type',
                AccessEventOperationalDecisionRecord::class
            )
            ->where(
                'subject_id',
                $context['decision']->id
            )
            ->where('event', 'created')
            ->firstOrFail();

        $executionActivity = Activity::query()
            ->where(
                'subject_type',
                AccessEventOperationalExecutionRecord::class
            )
            ->where(
                'subject_id',
                $context['execution']->id
            )
            ->where('event', 'created')
            ->firstOrFail();

        $manualActivity = activity(
            'access_control'
        )
            ->performedOn($context['event'])
            ->event(
                'access_event_flow_reprocessed'
            )
            ->withProperties([
                'status' => 'success',
                'all_duplicates' => true,

                'manual_review_release_used' => true,

                'manual_review_release_consumed' => true,

                'manual_review_id' => 'internal-review-timeline-id',

                'manual_review_consumption_id' => 'internal-consumption-timeline-id',

                'message' => 'A liberação da análise manual foi consumida.',
            ])
            ->log(
                'Fluxo do evento de acesso reprocessado'
            );
        $continuationActivity = activity(
            'access_control'
        )
            ->performedOn($context['event'])
            ->event(
                'access_event_manual_association_flow_continued'
            )
            ->withProperties([
                'status' => 'success',
                'all_duplicates' => false,
            ])
            ->log(
                'Fluxo continuado após associação manual'
            );

        $this->assertSame(
            VisitorRecord::class,
            data_get(
                $decisionActivity->properties,
                'vanguard_parent_type'
            )
        );

        $this->assertSame(
            VisitorRecord::class,
            data_get(
                $executionActivity->properties,
                'vanguard_parent_type'
            )
        );

        $action =
            AccessEventActivityLogTimelineAction::make();

        $method = new ReflectionMethod(
            $action,
            'getActivities'
        );

        $activities = $method->invoke(
            $action,
            $context['event']
        );

        $activityIds = $activities
            ->pluck('id')
            ->all();

        $this->assertContains(
            $decisionActivity->id,
            $activityIds
        );

        $this->assertContains(
            $executionActivity->id,
            $activityIds
        );

        $this->assertContains(
            $manualActivity->id,
            $activityIds
        );

        $this->assertContains(
            $continuationActivity->id,
            $activityIds
        );

        /*
         * O consumo enriquece a atividade de
         * reprocessamento já existente. Nenhum quinto item
         * duplicado deve ser criado na timeline.
         */
        $this->assertCount(
            4,
            $activities
        );

        $manualDetails =
            VanguardActivityLogPresenter::operationDetails(
                $manualActivity
            );

        $this->assertContains(
            [
                'label' => 'Liberação da análise manual',

                'value' => 'Consumida',
            ],
            $manualDetails
        );

        $this->assertContains(
            [
                'label' => 'Uso da liberação',
                'value' => 'Único',
            ],
            $manualDetails
        );

        $this->assertContains(
            [
                'label' => 'Nova análise para outra tentativa',

                'value' => 'Obrigatória',
            ],
            $manualDetails
        );

        $serialized = json_encode(
            $manualDetails,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        ) ?: '';

        foreach ([
            'internal-review-timeline-id',
            'internal-consumption-timeline-id',
        ] as $internalValue) {
            $this->assertStringNotContainsString(
                $internalValue,
                $serialized
            );
        }
    }

    public function test_it_presents_operational_children_with_friendly_historical_details(): void
    {
        $context = $this->createContext();

        $decisionActivity = Activity::query()
            ->where(
                'subject_type',
                AccessEventOperationalDecisionRecord::class
            )
            ->where(
                'subject_id',
                $context['decision']->id
            )
            ->firstOrFail();

        $executionActivity = Activity::query()
            ->where(
                'subject_type',
                AccessEventOperationalExecutionRecord::class
            )
            ->where(
                'subject_id',
                $context['execution']->id
            )
            ->firstOrFail();

        $context['decision']
            ->forceFill([
                'decision' => AccessEventOperationalDecision::NoAction,
            ])
            ->saveQuietly();

        $context['execution']
            ->forceFill([
                'status' => AccessEventOperationalExecutionStatus::Executed,
            ])
            ->saveQuietly();

        $decisionDetails =
            VanguardActivityLogPresenter::operationDetails(
                $decisionActivity
            );

        $executionDetails =
            VanguardActivityLogPresenter::operationDetails(
                $executionActivity
            );

        $this->assertContains(
            [
                'label' => 'Decisão',
                'value' => 'Candidato a entrada',
            ],
            $decisionDetails
        );

        $this->assertContains(
            [
                'label' => 'Execução automática habilitada',
                'value' => 'Não',
            ],
            $decisionDetails
        );

        $this->assertContains(
            [
                'label' => 'Origem',
                'value' => 'Automática',
            ],
            $executionDetails
        );

        $this->assertContains(
            [
                'label' => 'Status',
                'value' => 'Bloqueada',
            ],
            $executionDetails
        );

        $this->assertContains(
            [
                'label' => 'Execução automática permitida',
                'value' => 'Não',
            ],
            $executionDetails
        );
    }

    /**
     * @return array{
     *     event: AccessEventRecord,
     *     decision: AccessEventOperationalDecisionRecord,
     *     execution: AccessEventOperationalExecutionRecord
     * }
     */
    private function createContext(): array
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO HISTÓRICO SINTÉTICO',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE HISTÓRICO SINTÉTICO LTDA',
                'display_name' => 'UNIDADE HISTÓRICO SINTÉTICO',
                'unit_code' => 'HIS-01',
            ]);

        $device = AccessDeviceRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'code' => 'FAC-SIM-HIS-01',
            'name' => 'Facial sintético histórico',
            'provider' => 'simulator',
            'direction' => AccessDeviceDirection::Entry,
            'status' => AccessDeviceStatus::Active,
        ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE HISTÓRICO SINTÉTICO',
            'status' => VisitorStatus::Active,
        ]);

        $visit = VisitRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'status' => VisitStatus::Authorized,
            'purpose' => 'VALIDAÇÃO DO HISTÓRICO DO EVENTO',
            'expected_start_at' => now()->subMinutes(5),
            'expected_end_at' => now()->addHour(),
        ]);

        $event = AccessEventRecord::query()->create([
            'access_device_id' => $device->id,
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'visit_id' => $visit->id,
            'external_event_id' => 'synthetic-history-'
                .Str::lower(
                    Str::random(12)
                ),
            'external_person_id' => 'synthetic-history-person',
            'event_type' => 'face_recognition',
            'direction' => AccessEventDirection::Entry,
            'occurred_at' => now(),
            'status' => AccessEventStatus::Processed,
            'result_code' => 'processed',
            'result_message' => 'Evento processado.',
            'raw_payload' => [
                'synthetic' => true,
            ],
            'received_at' => now(),
            'processed_at' => now(),
            'processing_attempts' => 1,
        ]);

        $decision =
            AccessEventOperationalDecisionRecord::query()
                ->create([
                    'access_event_id' => $event->id,
                    'tenant_id' => $tenant->id,
                    'organization_id' => $organization->id,
                    'visitor_id' => $visitor->id,
                    'visit_id' => $visit->id,
                    'version' => 1,
                    'decision' => AccessEventOperationalDecision::CheckInCandidate,
                    'reason_code' => 'eligible_visit_found',
                    'reason_message' => 'Visita autorizada encontrada.',
                    'automatic_execution_enabled' => false,
                    'decided_at' => now(),
                ]);

        $execution =
            AccessEventOperationalExecutionRecord::query()
                ->create([
                    'operational_decision_id' => $decision->id,
                    'access_event_id' => $event->id,
                    'tenant_id' => $tenant->id,
                    'organization_id' => $organization->id,
                    'visitor_id' => $visitor->id,
                    'visit_id' => $visit->id,
                    'attempt_number' => 1,
                    'source' => AccessEventOperationalExecutionSource::Automatic,
                    'status' => AccessEventOperationalExecutionStatus::Blocked,
                    'reason_code' => 'automatic_execution_disabled',
                    'reason_message' => 'Execução automática desabilitada.',
                    'automatic_execution_allowed' => false,
                    'visit_status_before' => VisitStatus::Authorized->value,
                    'visit_status_after' => VisitStatus::Authorized->value,
                    'attempted_at' => now(),
                    'completed_at' => now(),
                ]);

        return [
            'event' => $event,
            'decision' => $decision,
            'execution' => $execution,
        ];
    }
}
