<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\Events\ManualAssociate;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\AccessControl\Events\ManualAssociate\ManualAssociateAccessEventCommand;
use App\Modules\Operations\Application\AccessControl\Events\ManualAssociate\ManualAssociateAccessEventException;
use App\Modules\Operations\Application\AccessControl\Events\ManualAssociate\ManualAssociateAccessEventUseCase;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventManualAssociationRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualAssociateAccessEventUseCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_associates_an_entry_event_without_changing_the_visit(): void
    {
        $scenario = $this->createScenario();

        $result = $this->execute(
            scenario: $scenario,
            visitId: $scenario['visit']->id,
        );

        $event = $scenario['event']->refresh();
        $visit = $scenario['visit']->refresh();

        $this->assertSame(
            AccessEventStatus::Processed,
            $result->status
        );

        $this->assertFalse($result->duplicate);

        $this->assertSame(
            $scenario['visitor']->id,
            $event->visitor_id
        );

        $this->assertSame(
            $visit->id,
            $event->visit_id
        );

        $this->assertNotNull(
            $event->processed_at
        );

        $this->assertSame(
            VisitStatus::Authorized,
            $visit->status
        );

        $association =
            AccessEventManualAssociationRecord::query()
                ->sole();

        $this->assertSame(
            $scenario['operator']->id,
            $association->operator_user_id
        );

        $this->assertSame(
            'manual_association_completed',
            $association->result_code
        );
    }

    public function test_it_associates_only_the_visitor_and_keeps_the_event_pending(): void
    {
        $scenario = $this->createScenario();

        $result = $this->execute(
            scenario: $scenario,
            visitId: null,
        );

        $event = $scenario['event']->refresh();

        $this->assertSame(
            AccessEventStatus::PendingAssociation,
            $result->status
        );

        $this->assertSame(
            $scenario['visitor']->id,
            $event->visitor_id
        );

        $this->assertNull($event->visit_id);
        $this->assertNull($event->processed_at);

        $this->assertSame(
            'manual_visitor_association_pending_visit',
            $event->result_code
        );
    }

    public function test_it_is_idempotent_for_the_same_request(): void
    {
        $scenario = $this->createScenario();
        $key = (string) Str::uuid();

        $first = $this->execute(
            scenario: $scenario,
            visitId: $scenario['visit']->id,
            idempotencyKey: $key,
        );

        $second = $this->execute(
            scenario: $scenario,
            visitId: $scenario['visit']->id,
            idempotencyKey: $key,
        );

        $this->assertFalse($first->duplicate);
        $this->assertTrue($second->duplicate);

        $this->assertSame(
            $first->associationId,
            $second->associationId
        );

        $this->assertSame(
            1,
            AccessEventManualAssociationRecord::query()
                ->count()
        );
    }

    public function test_it_rejects_an_event_that_is_not_pending_association(): void
    {
        $scenario = $this->createScenario();

        $scenario['event']
            ->forceFill([
                'status' => AccessEventStatus::Processed,
                'processed_at' => now(),
            ])
            ->saveQuietly();

        $this->expectException(
            ManualAssociateAccessEventException::class
        );

        $this->expectExceptionMessage(
            'Somente eventos aguardando associação podem ser associados manualmente.'
        );

        $this->execute(
            scenario: $scenario,
            visitId: $scenario['visit']->id,
        );
    }

    public function test_it_rejects_an_inactive_visitor(): void
    {
        $scenario = $this->createScenario();

        $scenario['visitor']->update([
            'status' => VisitorStatus::Inactive,
        ]);

        $this->expectException(
            ManualAssociateAccessEventException::class
        );

        $this->expectExceptionMessage(
            'O visitante selecionado não está ativo.'
        );

        $this->execute(
            scenario: $scenario,
            visitId: null,
        );
    }

    public function test_it_rejects_a_visitor_from_another_unit(): void
    {
        $scenario = $this->createScenario();

        $otherOrganization =
            $this->createOrganization(
                $scenario['tenant'],
                'UNIDADE EXTERNA',
                'EXT-01',
            );

        $otherVisitor = VisitorRecord::query()
            ->create([
                'tenant_id' => $scenario['tenant']->id,
                'organization_id' => $otherOrganization->id,
                'full_name' => 'VISITANTE DE OUTRA UNIDADE',
                'status' => VisitorStatus::Active,
            ]);

        $scenario['visitor'] = $otherVisitor;

        $this->expectException(
            ManualAssociateAccessEventException::class
        );

        $this->expectExceptionMessage(
            'O visitante selecionado não pertence ao grupo empresarial e à unidade do evento.'
        );

        $this->execute(
            scenario: $scenario,
            visitId: null,
        );
    }

    public function test_it_rejects_a_visit_from_another_visitor(): void
    {
        $scenario = $this->createScenario();

        $otherVisitor = VisitorRecord::query()
            ->create([
                'tenant_id' => $scenario['tenant']->id,
                'organization_id' => $scenario['organization']->id,
                'full_name' => 'OUTRO VISITANTE SINTÉTICO',
                'status' => VisitorStatus::Active,
            ]);

        $otherVisit = $this->createVisit(
            visitor: $otherVisitor,
            organization: $scenario['organization'],
            status: VisitStatus::Authorized,
        );

        $this->expectException(
            ManualAssociateAccessEventException::class
        );

        $this->expectExceptionMessage(
            'A visita selecionada não pertence ao visitante informado.'
        );

        $this->execute(
            scenario: $scenario,
            visitId: $otherVisit->id,
        );
    }

    public function test_an_exit_event_requires_an_in_progress_visit(): void
    {
        $scenario = $this->createScenario(
            direction: AccessEventDirection::Exit,
            visitStatus: VisitStatus::InProgress,
        );

        $result = $this->execute(
            scenario: $scenario,
            visitId: $scenario['visit']->id,
        );

        $this->assertSame(
            AccessEventStatus::Processed,
            $result->status
        );

        $this->assertSame(
            VisitStatus::InProgress,
            $scenario['visit']->refresh()->status
        );
    }

    public function test_it_rejects_an_incompatible_visit_without_persisting_changes(): void
    {
        $scenario = $this->createScenario(
            direction: AccessEventDirection::Entry,
            visitStatus: VisitStatus::Scheduled,
        );

        try {
            $this->execute(
                scenario: $scenario,
                visitId: $scenario['visit']->id,
            );

            $this->fail(
                'A associação com visita incompatível deveria ter sido rejeitada.'
            );
        } catch (
            ManualAssociateAccessEventException $exception
        ) {
            $this->assertSame(
                'A visita selecionada deve estar com a situação “Autorizada” para este evento.',
                $exception->getMessage()
            );
        }

        $event = $scenario['event']->refresh();

        $this->assertSame(
            AccessEventStatus::PendingAssociation,
            $event->status
        );

        $this->assertNull($event->visitor_id);
        $this->assertNull($event->visit_id);
        $this->assertNull($event->processed_at);

        $this->assertSame(
            VisitStatus::Scheduled,
            $scenario['visit']->refresh()->status
        );

        $this->assertSame(
            0,
            AccessEventManualAssociationRecord::query()
                ->count()
        );
    }

    public function test_it_rejects_reusing_an_idempotency_key_for_another_request(): void
    {
        $scenario = $this->createScenario();
        $key = (string) Str::uuid();

        $first = $this->execute(
            scenario: $scenario,
            visitId: $scenario['visit']->id,
            idempotencyKey: $key,
        );

        try {
            $this->execute(
                scenario: $scenario,
                visitId: null,
                idempotencyKey: $key,
            );

            $this->fail(
                'A reutilização conflitante da chave deveria ter sido rejeitada.'
            );
        } catch (
            ManualAssociateAccessEventException $exception
        ) {
            $this->assertSame(
                'A chave de idempotência já foi utilizada em outra associação manual.',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            1,
            AccessEventManualAssociationRecord::query()
                ->count()
        );

        $this->assertSame(
            $first->associationId,
            AccessEventManualAssociationRecord::query()
                ->sole()
                ->id
        );
    }

    public function test_it_rejects_a_missing_operator_without_persisting_changes(): void
    {
        $scenario = $this->createScenario();

        $missingOperatorId =
            ((int) User::query()->max('id')) + 1000;

        try {
            $this->execute(
                scenario: $scenario,
                visitId: $scenario['visit']->id,
                operatorUserId: $missingOperatorId,
            );

            $this->fail(
                'A associação sem operador válido deveria ter sido rejeitada.'
            );
        } catch (
            ManualAssociateAccessEventException $exception
        ) {
            $this->assertSame(
                'O operador responsável não foi encontrado.',
                $exception->getMessage()
            );
        }

        $event = $scenario['event']->refresh();

        $this->assertSame(
            AccessEventStatus::PendingAssociation,
            $event->status
        );

        $this->assertNull($event->visitor_id);
        $this->assertNull($event->visit_id);

        $this->assertSame(
            0,
            AccessEventManualAssociationRecord::query()
                ->count()
        );
    }

    /**
     * @param array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     event: AccessEventRecord,
     *     visitor: VisitorRecord,
     *     visit: VisitRecord,
     *     operator: User
     * } $scenario
     */
    private function execute(
        array $scenario,
        ?string $visitId,
        ?string $idempotencyKey = null,
        ?int $operatorUserId = null,
        ?string $reason = null,
    ) {
        return app(
            ManualAssociateAccessEventUseCase::class
        )->execute(
            new ManualAssociateAccessEventCommand(
                eventId: $scenario['event']->id,
                visitorId: $scenario['visitor']->id,
                visitId: $visitId,
                operatorUserId: $operatorUserId
                    ?? $scenario['operator']->id,
                reason: $reason
                    ?? 'Identidade conferida manualmente na portaria.',
                idempotencyKey: $idempotencyKey
                    ?? (string) Str::uuid(),
            )
        );
    }

    /**
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     event: AccessEventRecord,
     *     visitor: VisitorRecord,
     *     visit: VisitRecord,
     *     operator: User
     * }
     */
    private function createScenario(
        AccessEventDirection $direction =
            AccessEventDirection::Entry,
        VisitStatus $visitStatus =
            VisitStatus::Authorized,
    ): array {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO ASSOCIAÇÃO MANUAL',
            'status' => 'active',
        ]);

        $organization = $this->createOrganization(
            $tenant,
            'UNIDADE ASSOCIAÇÃO MANUAL',
            'MAN-01',
        );

        $device = AccessDeviceRecord::query()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'code' => 'FAC-MAN-01',
                'name' => 'LEITOR ASSOCIAÇÃO MANUAL',
                'provider' => 'simulator',
                'direction' => match ($direction) {
                    AccessEventDirection::Entry => AccessDeviceDirection::Entry,
                    AccessEventDirection::Exit => AccessDeviceDirection::Exit,
                },
                'status' => AccessDeviceStatus::Active,
            ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE ASSOCIAÇÃO MANUAL',
            'status' => VisitorStatus::Active,
        ]);

        $visit = $this->createVisit(
            visitor: $visitor,
            organization: $organization,
            status: $visitStatus,
        );

        $event = AccessEventRecord::query()->create([
            'access_device_id' => $device->id,
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'external_event_id' => 'manual-'.Str::uuid(),
            'event_type' => 'face_recognition',
            'direction' => $direction,
            'occurred_at' => '2026-07-16 14:30:00',
            'status' => AccessEventStatus::PendingAssociation,
            'received_at' => '2026-07-16 14:30:00',
            'processing_attempts' => 1,
        ]);

        return [
            'tenant' => $tenant,
            'organization' => $organization,
            'event' => $event,
            'visitor' => $visitor,
            'visit' => $visit,
            'operator' => User::factory()->create([
                'name' => 'OPERADOR ASSOCIAÇÃO MANUAL',
            ]),
        ];
    }

    private function createOrganization(
        TenantRecord $tenant,
        string $name,
        string $code,
    ): OrganizationRecord {
        return OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => "{$name} LTDA",
            'display_name' => $name,
            'unit_code' => $code,
        ]);
    }

    private function createVisit(
        VisitorRecord $visitor,
        OrganizationRecord $organization,
        VisitStatus $status,
    ): VisitRecord {
        return VisitRecord::query()->create([
            'tenant_id' => $organization->tenant_id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'status' => $status,
            'purpose' => 'VISITA PARA ASSOCIAÇÃO MANUAL',
            'expected_start_at' => '2026-07-16 14:00:00',
            'expected_end_at' => '2026-07-16 16:00:00',
        ]);
    }
}
