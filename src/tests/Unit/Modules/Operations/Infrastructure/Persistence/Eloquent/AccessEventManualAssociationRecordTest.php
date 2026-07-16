<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class AccessEventManualAssociationRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_preserves_an_immutable_manual_association_ledger(): void
    {
        [
            $tenant,
            $organization,
            $event,
            $visitor,
            $visit,
            $user,
        ] = $this->createScenario();

        $association =
            AccessEventManualAssociationRecord::query()
                ->create([
                    'access_event_id' => $event->id,
                    'tenant_id' => $tenant->id,
                    'organization_id' => $organization->id,
                    'idempotency_key' => (string) Str::uuid(),
                    'previous_visitor_id' => null,
                    'previous_visit_id' => null,
                    'selected_visitor_id' => $visitor->id,
                    'selected_visit_id' => $visit->id,
                    'operator_user_id' => $user->id,
                    'operator_name' => $user->name,
                    'previous_visitor_name' => null,
                    'previous_visit_reference' => null,
                    'selected_visitor_name' => $visitor->full_name,
                    'selected_visit_reference' => $visit->purpose,
                    'reason' => 'Associação conferida manualmente na portaria.',
                    'resulting_status' => AccessEventStatus::Processed,
                    'result_code' => 'manual_association_completed',
                    'result_message' => 'Evento associado manualmente sem alterar a situação da visita.',
                    'associated_at' => '2026-07-16 15:00:00',
                ]);

        $this->assertNotEmpty(
            $association->id
        );

        $this->assertSame(
            AccessEventStatus::Processed,
            $association->resulting_status
        );

        $this->assertSame(
            $event->id,
            $association->accessEvent->id
        );

        $this->assertSame(
            $visitor->id,
            $association->selectedVisitor->id
        );

        $this->assertSame(
            $visit->id,
            $association->selectedVisit->id
        );

        $this->assertSame(
            $user->id,
            $association->operatorUser->id
        );

        $this->assertTrue(
            $event->manualAssociations()
                ->whereKey($association->id)
                ->exists()
        );

        $this->assertSame(
            $association->id,
            $event
                ->latestManualAssociation()
                ->first()
                ?->id
        );
    }

    public function test_it_rejects_updates_and_deletions(): void
    {
        [
            $tenant,
            $organization,
            $event,
            $visitor,
            $visit,
            $user,
        ] = $this->createScenario();

        $association =
            AccessEventManualAssociationRecord::query()
                ->create([
                    'access_event_id' => $event->id,
                    'tenant_id' => $tenant->id,
                    'organization_id' => $organization->id,
                    'idempotency_key' => (string) Str::uuid(),
                    'selected_visitor_id' => $visitor->id,
                    'selected_visit_id' => $visit->id,
                    'operator_user_id' => $user->id,
                    'operator_name' => $user->name,
                    'selected_visitor_name' => $visitor->full_name,
                    'selected_visit_reference' => $visit->purpose,
                    'reason' => 'Registro imutável para teste.',
                    'resulting_status' => AccessEventStatus::Processed,
                    'result_code' => 'manual_association_completed',
                    'associated_at' => now(),
                ]);

        try {
            $association->update([
                'reason' => 'Tentativa de alteração.',
            ]);

            $this->fail(
                'A atualização do ledger deveria ter sido bloqueada.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                'Associações manuais de eventos são registros imutáveis.',
                $exception->getMessage()
            );
        }

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'Associações manuais de eventos não podem ser excluídas.'
        );

        $association->delete();
    }

    public function test_idempotency_key_is_unique(): void
    {
        [
            $tenant,
            $organization,
            $event,
            $visitor,
            $visit,
            $user,
        ] = $this->createScenario();

        $key = (string) Str::uuid();

        $data = [
            'access_event_id' => $event->id,
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'idempotency_key' => $key,
            'selected_visitor_id' => $visitor->id,
            'selected_visit_id' => $visit->id,
            'operator_user_id' => $user->id,
            'operator_name' => $user->name,
            'selected_visitor_name' => $visitor->full_name,
            'selected_visit_reference' => $visit->purpose,
            'reason' => 'Teste de idempotência.',
            'resulting_status' => AccessEventStatus::Processed,
            'result_code' => 'manual_association_completed',
            'associated_at' => now(),
        ];

        AccessEventManualAssociationRecord::query()
            ->create($data);

        $this->expectException(
            QueryException::class
        );

        AccessEventManualAssociationRecord::query()
            ->create($data);
    }

    /**
     * @return array{
     *     0: TenantRecord,
     *     1: OrganizationRecord,
     *     2: AccessEventRecord,
     *     3: VisitorRecord,
     *     4: VisitRecord,
     *     5: User
     * }
     */
    private function createScenario(): array
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO LEDGER SINTÉTICO',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE LEDGER SINTÉTICA LTDA',
                'display_name' => 'UNIDADE LEDGER SINTÉTICA',
                'unit_code' => 'LED-01',
            ]);

        $device = AccessDeviceRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'code' => 'FAC-LED-01',
            'name' => 'LEITOR SINTÉTICO DO LEDGER',
            'provider' => 'simulator',
            'direction' => AccessDeviceDirection::Entry,
            'status' => AccessDeviceStatus::Active,
        ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE SINTÉTICO DO LEDGER',
            'status' => VisitorStatus::Active,
        ]);

        $visit = VisitRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'status' => VisitStatus::Authorized,
            'purpose' => 'VISITA SINTÉTICA DO LEDGER',
            'expected_start_at' => '2026-07-16 14:00:00',
            'expected_end_at' => '2026-07-16 16:00:00',
        ]);

        $event = AccessEventRecord::query()->create([
            'access_device_id' => $device->id,
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'external_event_id' => 'ledger-'.Str::uuid(),
            'event_type' => 'face_recognition',
            'direction' => AccessEventDirection::Entry,
            'occurred_at' => '2026-07-16 14:30:00',
            'status' => AccessEventStatus::PendingAssociation,
            'received_at' => '2026-07-16 14:30:00',
            'processing_attempts' => 1,
        ]);

        $user = User::factory()->create([
            'name' => 'OPERADOR SINTÉTICO DO LEDGER',
        ]);

        return [
            $tenant,
            $organization,
            $event,
            $visitor,
            $visit,
            $user,
        ];
    }
}
