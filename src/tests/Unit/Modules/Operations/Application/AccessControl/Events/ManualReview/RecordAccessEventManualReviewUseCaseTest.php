<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\Events\ManualReview;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\AccessControl\Events\ManualReview\RecordAccessEventManualReviewCommand;
use App\Modules\Operations\Application\AccessControl\Events\ManualReview\RecordAccessEventManualReviewException;
use App\Modules\Operations\Application\AccessControl\Events\ManualReview\RecordAccessEventManualReviewUseCase;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventManualReviewDisposition;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventManualReviewRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RecordAccessEventManualReviewUseCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_it_resolves_from_the_container(): void
    {
        $this->assertInstanceOf(
            RecordAccessEventManualReviewUseCase::class,
            app(RecordAccessEventManualReviewUseCase::class)
        );
    }

    public function test_it_records_a_manual_review_without_changing_the_event_or_visit(): void
    {
        $scenario = $this->createScenario();

        $result = $this->execute(
            scenario: $scenario,
            disposition: AccessEventManualReviewDisposition::PendingCorrection,
            notes: 'Visitante orientado a cadastrar uma foto facial válida.',
        );

        $this->assertFalse($result->duplicate);

        $record =
            AccessEventManualReviewRecord::query()
                ->firstOrFail();

        $this->assertSame(
            $scenario['event']->id,
            $record->access_event_id
        );

        $this->assertSame(
            $scenario['decision']->id,
            $record->operational_decision_id
        );

        $this->assertSame(
            AccessEventManualReviewDisposition::PendingCorrection,
            $record->disposition
        );

        $this->assertSame(
            'visitor_photo_missing',
            $record->decision_reason_code
        );

        $this->assertSame(
            $scenario['operator']->id,
            $record->operator_user_id
        );

        $this->assertSame(
            $scenario['operator']->name,
            $record->operator_name
        );

        $this->assertSame(
            VisitStatus::Authorized,
            $scenario['visit']->refresh()->status
        );

        $this->assertNull(
            $scenario['visit']->checked_in_at
        );

        $this->assertSame(
            AccessEventStatus::Processed,
            $scenario['event']->refresh()->status
        );

        $this->assertSame(
            1,
            $scenario['event']
                ->manualReviews()
                ->count()
        );

        $this->assertSame(
            $record->id,
            $scenario['event']
                ->latestManualReview
                ?->id
        );

        Http::assertSentCount(0);
    }

    public function test_it_is_idempotent_for_the_same_review(): void
    {
        $scenario = $this->createScenario();
        $key = (string) Str::uuid();

        $first = $this->execute(
            scenario: $scenario,
            disposition: AccessEventManualReviewDisposition::ReadyForReprocessing,
            notes: 'A correção foi realizada e o evento pode ser reavaliado.',
            idempotencyKey: $key,
        );

        $second = $this->execute(
            scenario: $scenario,
            disposition: AccessEventManualReviewDisposition::ReadyForReprocessing,
            notes: 'A correção foi realizada e o evento pode ser reavaliado.',
            idempotencyKey: $key,
        );

        $this->assertFalse($first->duplicate);
        $this->assertTrue($second->duplicate);

        $this->assertSame(
            $first->reviewId,
            $second->reviewId
        );

        $this->assertSame(
            1,
            AccessEventManualReviewRecord::query()
                ->count()
        );
    }

    public function test_it_rejects_reusing_the_key_for_another_review(): void
    {
        $scenario = $this->createScenario();
        $key = (string) Str::uuid();

        $this->execute(
            scenario: $scenario,
            disposition: AccessEventManualReviewDisposition::PendingCorrection,
            notes: 'A pendência permanece aguardando correção operacional.',
            idempotencyKey: $key,
        );

        $this->expectException(
            RecordAccessEventManualReviewException::class
        );

        $this->expectExceptionMessage(
            'A chave de idempotência já foi utilizada em outra análise manual.'
        );

        $this->execute(
            scenario: $scenario,
            disposition: AccessEventManualReviewDisposition::ResolvedWithoutOperation,
            notes: 'O evento foi analisado e não exige operação de entrada.',
            idempotencyKey: $key,
        );
    }

    public function test_it_rejects_an_event_without_a_current_manual_review_decision(): void
    {
        $scenario = $this->createScenario();

        AccessEventOperationalDecisionRecord::query()
            ->create([
                'access_event_id' => $scenario['event']->id,
                'tenant_id' => $scenario['tenant']->id,
                'organization_id' => $scenario['organization']->id,
                'visitor_id' => $scenario['visitor']->id,
                'visit_id' => $scenario['visit']->id,
                'version' => 2,
                'decision' => AccessEventOperationalDecision::CheckInCandidate,
                'reason_code' => 'check_in_candidate',
                'reason_message' => 'Evento elegível para entrada.',
                'automatic_execution_enabled' => false,
                'decided_at' => now(),
            ]);

        $this->expectException(
            RecordAccessEventManualReviewException::class
        );

        $this->expectExceptionMessage(
            'Somente eventos com decisão atual de revisão manual podem receber uma análise.'
        );

        $this->execute(
            scenario: $scenario,
            disposition: AccessEventManualReviewDisposition::PendingCorrection,
            notes: 'Esta análise não deveria ser registrada.',
        );
    }

    public function test_it_rejects_a_missing_operator(): void
    {
        $scenario = $this->createScenario();

        $this->expectException(
            RecordAccessEventManualReviewException::class
        );

        $this->expectExceptionMessage(
            'O operador responsável não foi encontrado.'
        );

        $this->execute(
            scenario: $scenario,
            disposition: AccessEventManualReviewDisposition::PendingCorrection,
            notes: 'A revisão possui uma observação válida.',
            operatorUserId: 999999,
        );
    }

    public function test_it_rejects_an_operator_without_permission(): void
    {
        $scenario = $this->createScenario();

        $scenario['operator']->revokePermissionTo(
            'ResolveManualReview:AccessEventRecord'
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $this->expectException(
            RecordAccessEventManualReviewException::class
        );

        $this->expectExceptionMessage(
            'O operador não possui autorização para analisar este evento.'
        );

        $this->execute(
            scenario: $scenario,
            disposition: AccessEventManualReviewDisposition::PendingCorrection,
            notes: 'A revisão possui uma observação válida.',
        );
    }

    public function test_it_rejects_an_operator_without_unit_access(): void
    {
        $scenario = $this->createScenario();

        $scenario['operator']
            ->organizations()
            ->detach(
                $scenario['organization']->id
            );

        $this->expectException(
            RecordAccessEventManualReviewException::class
        );

        $this->expectExceptionMessage(
            'O operador não possui autorização para analisar este evento.'
        );

        $this->execute(
            scenario: $scenario,
            disposition: AccessEventManualReviewDisposition::PendingCorrection,
            notes: 'A revisão possui uma observação válida.',
        );
    }

    public function test_it_validates_the_review_notes(): void
    {
        $scenario = $this->createScenario();

        $this->expectException(
            RecordAccessEventManualReviewException::class
        );

        $this->expectExceptionMessage(
            'Informe uma observação com pelo menos 10 caracteres.'
        );

        $this->execute(
            scenario: $scenario,
            disposition: AccessEventManualReviewDisposition::PendingCorrection,
            notes: 'Curta',
        );
    }

    public function test_the_manual_review_ledger_cannot_be_updated(): void
    {
        $scenario = $this->createScenario();

        $this->execute(
            scenario: $scenario,
            disposition: AccessEventManualReviewDisposition::PendingCorrection,
            notes: 'A pendência permanece aguardando correção operacional.',
        );

        $record = AccessEventManualReviewRecord::query()
            ->firstOrFail();

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'As análises manuais de eventos de acesso são imutáveis.'
        );

        $record->update([
            'notes' => 'Tentativa indevida de alteração.',
        ]);
    }

    public function test_the_manual_review_ledger_cannot_be_deleted(): void
    {
        $scenario = $this->createScenario();

        $this->execute(
            scenario: $scenario,
            disposition: AccessEventManualReviewDisposition::PendingCorrection,
            notes: 'A pendência permanece aguardando correção operacional.',
        );

        $record = AccessEventManualReviewRecord::query()
            ->firstOrFail();

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'As análises manuais de eventos de acesso não podem ser excluídas.'
        );

        $record->delete();
    }

    /**
     * @param array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     event: AccessEventRecord,
     *     visitor: VisitorRecord,
     *     visit: VisitRecord,
     *     decision: AccessEventOperationalDecisionRecord,
     *     operator: User
     * } $scenario
     */
    private function execute(
        array $scenario,
        AccessEventManualReviewDisposition $disposition,
        string $notes,
        ?string $idempotencyKey = null,
        ?int $operatorUserId = null,
    ) {
        return app(
            RecordAccessEventManualReviewUseCase::class
        )->execute(
            new RecordAccessEventManualReviewCommand(
                eventId: $scenario['event']->id,
                operatorUserId: $operatorUserId
                    ?? $scenario['operator']->id,
                disposition: $disposition,
                notes: $notes,
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
     *     decision: AccessEventOperationalDecisionRecord,
     *     operator: User
     * }
     */
    private function createScenario(): array
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO REVISÃO MANUAL A1',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE REVISÃO MANUAL A1 LTDA',
                'display_name' => 'UNIDADE REVISÃO MANUAL A1',
                'unit_code' => 'REV-01',
            ]);

        $device = AccessDeviceRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'code' => 'FAC-REV-A1',
            'name' => 'LEITOR REVISÃO MANUAL A1',
            'provider' => 'simulator',
            'direction' => AccessDeviceDirection::Entry,
            'status' => AccessDeviceStatus::Active,
        ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE REVISÃO MANUAL A1',
            'status' => VisitorStatus::Active,
        ]);

        $visit = VisitRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'status' => VisitStatus::Authorized,
            'purpose' => 'VALIDAÇÃO REVISÃO MANUAL A1',
            'expected_start_at' => now(),
            'expected_end_at' => now()->addHour(),
        ]);

        $event = AccessEventRecord::query()->create([
            'access_device_id' => $device->id,
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'visit_id' => $visit->id,
            'external_event_id' => 'review-a1-'.Str::uuid(),
            'event_type' => 'face_recognition',
            'direction' => AccessEventDirection::Entry,
            'occurred_at' => now(),
            'status' => AccessEventStatus::Processed,
            'result_code' => 'association_completed',
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
                    'decision' => AccessEventOperationalDecision::ManualReview,
                    'reason_code' => 'visitor_photo_missing',
                    'reason_message' => 'O visitante não possui foto facial local.',
                    'automatic_execution_enabled' => false,
                    'decided_at' => now(),
                ]);

        $permission = Permission::findOrCreate(
            'ResolveManualReview:AccessEventRecord',
            'web'
        );

        $operator = User::factory()->create([
            'name' => 'OPERADOR REVISÃO MANUAL A1',
        ]);

        $operator->givePermissionTo($permission);

        $operator->organizations()->attach(
            $organization->id,
            [
                'role' => 'operator',
                'is_active' => true,
                'granted_at' => now(),
            ]
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        return [
            'tenant' => $tenant,
            'organization' => $organization,
            'event' => $event,
            'visitor' => $visitor,
            'visit' => $visit,
            'decision' => $decision,
            'operator' => $operator,
        ];
    }
}
