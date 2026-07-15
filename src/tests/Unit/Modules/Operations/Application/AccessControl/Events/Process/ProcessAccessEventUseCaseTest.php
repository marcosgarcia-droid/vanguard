<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\Events\Process;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\AccessControl\Events\Process\ProcessAccessEventCommand;
use App\Modules\Operations\Application\AccessControl\Events\Process\ProcessAccessEventException;
use App\Modules\Operations\Application\AccessControl\Events\Process\ProcessAccessEventRepository;
use App\Modules\Operations\Application\AccessControl\Events\Process\ProcessAccessEventResult;
use App\Modules\Operations\Application\AccessControl\Events\Process\ProcessAccessEventUseCase;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ProcessAccessEventUseCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_it_associates_an_entry_event_without_changing_the_visit(): void
    {
        $context = $this->createContext(
            direction: AccessEventDirection::Entry,
            visitStatuses: [
                VisitStatus::Authorized,
            ],
        );

        $result = $this->process(
            $context['event']
        );

        $event = $context['event']->fresh();
        $visit = $context['visits'][0]->fresh();

        $this->assertSame(
            AccessEventStatus::Processed,
            $result->status
        );

        $this->assertSame(
            $context['visitor']->id,
            $event?->visitor_id
        );

        $this->assertSame(
            $visit?->id,
            $event?->visit_id
        );

        $this->assertSame(
            'association_completed',
            $event?->result_code
        );

        $this->assertSame(
            1,
            $event?->processing_attempts
        );

        $this->assertNotNull(
            $event?->processed_at
        );

        $this->assertSame(
            VisitStatus::Authorized,
            $visit?->status
        );

        $this->assertNull(
            $visit?->checked_in_at
        );

        Http::assertSentCount(0);
    }

    public function test_it_associates_an_exit_event_without_completing_the_visit(): void
    {
        $context = $this->createContext(
            direction: AccessEventDirection::Exit,
            visitStatuses: [
                VisitStatus::InProgress,
            ],
        );

        $result = $this->process(
            $context['event']
        );

        $visit = $context['visits'][0]->fresh();

        $this->assertSame(
            AccessEventStatus::Processed,
            $result->status
        );

        $this->assertSame(
            VisitStatus::InProgress,
            $visit?->status
        );

        $this->assertNull(
            $visit?->checked_out_at
        );

        Http::assertSentCount(0);
    }

    public function test_it_keeps_an_event_without_person_reference_pending(): void
    {
        $context = $this->createContext(
            externalPersonId: null,
            createVisitor: false,
        );

        $result = $this->process(
            $context['event']
        );

        $this->assertSame(
            AccessEventStatus::PendingAssociation,
            $result->status
        );

        $this->assertSame(
            'person_reference_missing',
            $result->resultCode
        );

        $this->assertNull(
            $result->visitorId
        );

        $this->assertNull(
            $result->visitId
        );

        $this->assertSame(
            1,
            $result->processingAttempts
        );
    }

    public function test_it_does_not_associate_a_visitor_from_another_unit(): void
    {
        $context = $this->createContext(
            createVisitor: false,
        );

        $otherOrganization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $context['tenant']->id,
                'status' => 'active',
                'legal_name' => 'OUTRA UNIDADE DEMONSTRAÇÃO LTDA',
                'display_name' => 'OUTRA UNIDADE DEMONSTRAÇÃO',
            ]);

        VisitorRecord::query()->create([
            'tenant_id' => $context['tenant']->id,
            'organization_id' => $otherOrganization->id,
            'full_name' => 'Visitante de outra unidade',
            'status' => VisitorStatus::Active,
            'external_source' => 'simulator',
            'external_id' => 'synthetic-person-001',
        ]);

        $result = $this->process(
            $context['event']
        );

        $this->assertSame(
            AccessEventStatus::PendingAssociation,
            $result->status
        );

        $this->assertSame(
            'visitor_not_found',
            $result->resultCode
        );

        $this->assertNull(
            $result->visitorId
        );

        $this->assertNull(
            $result->visitId
        );
    }

    public function test_it_rejects_an_inactive_visitor(): void
    {
        $context = $this->createContext(
            visitorStatus: VisitorStatus::Inactive,
        );

        $result = $this->process(
            $context['event']
        );

        $this->assertSame(
            AccessEventStatus::PendingAssociation,
            $result->status
        );

        $this->assertSame(
            'visitor_inactive',
            $result->resultCode
        );

        $this->assertNull(
            $result->visitorId
        );

        $this->assertNull(
            $result->visitId
        );
    }

    public function test_it_associates_only_the_visitor_when_no_visit_is_eligible(): void
    {
        $context = $this->createContext(
            visitStatuses: [
                VisitStatus::Scheduled,
            ],
        );

        $result = $this->process(
            $context['event']
        );

        $this->assertSame(
            AccessEventStatus::PendingAssociation,
            $result->status
        );

        $this->assertSame(
            'visitor_associated_no_visit',
            $result->resultCode
        );

        $this->assertSame(
            $context['visitor']->id,
            $result->visitorId
        );

        $this->assertNull(
            $result->visitId
        );
    }

    public function test_it_does_not_choose_between_multiple_eligible_visits(): void
    {
        $context = $this->createContext(
            visitStatuses: [
                VisitStatus::Authorized,
                VisitStatus::Authorized,
            ],
        );

        $result = $this->process(
            $context['event']
        );

        $this->assertSame(
            AccessEventStatus::PendingAssociation,
            $result->status
        );

        $this->assertSame(
            'multiple_eligible_visits',
            $result->resultCode
        );

        $this->assertSame(
            $context['visitor']->id,
            $result->visitorId
        );

        $this->assertNull(
            $result->visitId
        );

        foreach ($context['visits'] as $visit) {
            $this->assertSame(
                VisitStatus::Authorized,
                $visit->fresh()?->status
            );
        }
    }

    public function test_it_processes_a_pending_event_after_an_eligible_visit_is_created(): void
    {
        $context = $this->createContext(
            visitStatuses: [],
        );

        $first = $this->process(
            $context['event']
        );

        $this->assertSame(
            AccessEventStatus::PendingAssociation,
            $first->status
        );

        $this->assertSame(
            'visitor_associated_no_visit',
            $first->resultCode
        );

        $this->assertSame(
            1,
            $first->processingAttempts
        );

        $visit = VisitRecord::query()->create([
            'tenant_id' => $context['tenant']->id,
            'organization_id' => $context['organization']->id,
            'visitor_id' => $context['visitor']->id,
            'status' => VisitStatus::Authorized,
            'purpose' => 'Visita criada após o primeiro processamento',
            'expected_start_at' => now()->addHour(),
        ]);

        $second = $this->process(
            $context['event']
        );

        $this->assertSame(
            AccessEventStatus::Processed,
            $second->status
        );

        $this->assertSame(
            'association_completed',
            $second->resultCode
        );

        $this->assertSame(
            $context['visitor']->id,
            $second->visitorId
        );

        $this->assertSame(
            $visit->id,
            $second->visitId
        );

        $this->assertSame(
            2,
            $second->processingAttempts
        );

        $this->assertFalse(
            $second->duplicate
        );

        $visit->refresh();

        $this->assertSame(
            VisitStatus::Authorized,
            $visit->status
        );

        $this->assertNull(
            $visit->checked_in_at
        );

        Http::assertSentCount(0);
    }

    public function test_it_is_idempotent_after_successful_processing(): void
    {
        $context = $this->createContext(
            visitStatuses: [
                VisitStatus::Authorized,
            ],
        );

        $first = $this->process(
            $context['event']
        );

        $second = $this->process(
            $context['event']
        );

        $this->assertFalse(
            $first->duplicate
        );

        $this->assertTrue(
            $second->duplicate
        );

        $this->assertSame(
            1,
            $second->processingAttempts
        );

        $this->assertSame(
            $first->visitId,
            $second->visitId
        );
    }

    public function test_it_reports_an_event_that_does_not_exist(): void
    {
        $this->expectException(
            ProcessAccessEventException::class
        );

        $this->expectExceptionMessage(
            'O evento de acesso não foi encontrado.'
        );

        app(
            ProcessAccessEventUseCase::class
        )->execute(
            new ProcessAccessEventCommand(
                eventId: (string) Str::uuid(),
            )
        );
    }

    public function test_it_marks_an_unexpected_processing_failure(): void
    {
        $repository =
            new class implements ProcessAccessEventRepository
            {
                public bool $failed = false;

                public function process(
                    string $eventId
                ): ?ProcessAccessEventResult {
                    throw new RuntimeException(
                        'Falha técnica simulada.'
                    );
                }

                public function markFailed(
                    string $eventId,
                    string $message
                ): void {
                    $this->failed = true;
                }
            };

        $useCase = new ProcessAccessEventUseCase(
            $repository
        );

        try {
            $useCase->execute(
                new ProcessAccessEventCommand(
                    eventId: (string) Str::uuid(),
                )
            );

            $this->fail(
                'Era esperada uma falha controlada.'
            );
        } catch (
            ProcessAccessEventException $exception
        ) {
            $this->assertSame(
                'Não foi possível processar o evento de acesso.',
                $exception->getMessage()
            );
        }

        $this->assertTrue(
            $repository->failed
        );
    }

    private function process(
        AccessEventRecord $event
    ): ProcessAccessEventResult {
        return app(
            ProcessAccessEventUseCase::class
        )->execute(
            new ProcessAccessEventCommand(
                eventId: $event->id,
            )
        );
    }

    /**
     * @param  array<int, VisitStatus>  $visitStatuses
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     device: AccessDeviceRecord,
     *     visitor: ?VisitorRecord,
     *     visits: array<int, VisitRecord>,
     *     event: AccessEventRecord
     * }
     */
    private function createContext(
        AccessEventDirection $direction =
            AccessEventDirection::Entry,
        array $visitStatuses = [],
        VisitorStatus $visitorStatus =
            VisitorStatus::Active,
        ?string $externalPersonId =
            'synthetic-person-001',
        bool $createVisitor = true,
    ): array {
        $tenant = TenantRecord::query()->create([
            'name' => 'GRUPO DEMONSTRAÇÃO',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE DEMONSTRAÇÃO LTDA',
                'display_name' => 'UNIDADE DEMONSTRAÇÃO',
            ]);

        $device = AccessDeviceRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'code' => $direction
                === AccessEventDirection::Entry
                    ? 'FAC-SIM-ENT-01'
                    : 'FAC-SIM-SAI-01',
            'name' => 'Facial simulado',
            'provider' => 'simulator',
            'direction' => $direction
                === AccessEventDirection::Entry
                    ? AccessDeviceDirection::Entry
                    : AccessDeviceDirection::Exit,
            'status' => AccessDeviceStatus::Active,
        ]);

        $visitor = null;
        $visits = [];

        if ($createVisitor) {
            $visitor = VisitorRecord::query()->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'full_name' => 'Pessoa Visitante Sintética',
                'status' => $visitorStatus,
                'external_source' => 'simulator',
                'external_id' => 'synthetic-person-001',
            ]);

            foreach (
                $visitStatuses as $index => $visitStatus
            ) {
                $visits[] =
                    VisitRecord::query()->create([
                        'tenant_id' => $tenant->id,
                        'organization_id' => $organization->id,
                        'visitor_id' => $visitor->id,
                        'status' => $visitStatus,
                        'purpose' => 'Visita sintética '.($index + 1),
                        'expected_start_at' => now()->addMinutes(
                            30 + $index
                        ),
                    ]);
            }
        }

        $event = AccessEventRecord::query()->create([
            'access_device_id' => $device->id,
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'external_event_id' => 'synthetic-event-001',
            'external_person_id' => $externalPersonId,
            'event_type' => 'face_recognition',
            'direction' => $direction,
            'occurred_at' => new DateTimeImmutable(
                '2026-07-15 12:00:00'
            ),
            'status' => AccessEventStatus::PendingAssociation,
            'result_code' => 'pending_association',
            'processing_attempts' => 0,
        ]);

        return compact(
            'tenant',
            'organization',
            'device',
            'visitor',
            'visits',
            'event',
        );
    }
}
