<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\Events\Reprocess;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\AccessControl\Events\Reprocess\ReprocessAccessEventFlowException;
use App\Modules\Operations\Application\AccessControl\Events\Reprocess\ReprocessAccessEventFlowRepository;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventManualReviewDisposition;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventManualReviewRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReprocessAccessEventFlowRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_allows_a_regular_operational_reprocessing(): void
    {
        $scenario = $this->createScenario(
            AccessEventOperationalDecision::CheckInCandidate
        );

        $context = $this->repository()->prepare(
            eventId: $scenario['event']->id,
            operatorUserId: $scenario['operator']->id,
        );

        $this->assertNotNull($context);
        $this->assertFalse(
            $context->manualReviewReleaseUsed
        );
    }

    public function test_it_rejects_manual_review_without_human_analysis(): void
    {
        $scenario = $this->createScenario();

        $this->expectException(
            ReprocessAccessEventFlowException::class
        );

        $this->expectExceptionMessage(
            'Registre uma análise manual antes de reprocessar este evento.'
        );

        $this->repository()->prepare(
            eventId: $scenario['event']->id,
            operatorUserId: $scenario['operator']->id,
        );
    }

    public function test_it_rejects_a_review_still_waiting_for_correction(): void
    {
        $scenario = $this->createScenario();

        $this->createReview(
            $scenario,
            AccessEventManualReviewDisposition::PendingCorrection
        );

        $this->expectException(
            ReprocessAccessEventFlowException::class
        );

        $this->expectExceptionMessage(
            'A análise manual permanece aguardando correção.'
        );

        $this->repository()->prepare(
            eventId: $scenario['event']->id,
            operatorUserId: $scenario['operator']->id,
        );
    }

    public function test_it_allows_a_review_ready_for_reprocessing(): void
    {
        $scenario = $this->createScenario();

        $review = $this->createReview(
            $scenario,
            AccessEventManualReviewDisposition::ReadyForReprocessing
        );

        $context = $this->repository()->prepare(
            eventId: $scenario['event']->id,
            operatorUserId: $scenario['operator']->id,
        );

        $this->assertNotNull($context);

        $this->assertTrue(
            $context->manualReviewReleaseUsed
        );

        $this->assertSame(
            $scenario['decision']->id,
            $context->decisionId
        );

        $this->assertSame(
            $review->id,
            $context->manualReviewId
        );
    }

    public function test_it_rejects_a_review_resolved_without_operation(): void
    {
        $scenario = $this->createScenario();

        $this->createReview(
            $scenario,
            AccessEventManualReviewDisposition::ResolvedWithoutOperation
        );

        $this->expectException(
            ReprocessAccessEventFlowException::class
        );

        $this->expectExceptionMessage(
            'A revisão manual foi encerrada sem operação e não pode ser reprocessada.'
        );

        $this->repository()->prepare(
            eventId: $scenario['event']->id,
            operatorUserId: $scenario['operator']->id,
        );
    }

    public function test_it_rejects_an_analysis_from_an_old_decision(): void
    {
        $scenario = $this->createScenario();

        $this->createReview(
            $scenario,
            AccessEventManualReviewDisposition::ReadyForReprocessing
        );

        AccessEventOperationalDecisionRecord::query()
            ->create([
                'access_event_id' => $scenario['event']->id,

                'tenant_id' => $scenario['event']->tenant_id,

                'organization_id' => $scenario['event']->organization_id,

                'visitor_id' => null,
                'visit_id' => null,
                'version' => 2,

                'decision' => AccessEventOperationalDecision::ManualReview,

                'reason_code' => 'manual_review_recalculated',

                'reason_message' => 'Uma nova decisão exige nova análise.',

                'automatic_execution_enabled' => false,

                'decided_at' => now(),
            ]);

        $this->expectException(
            ReprocessAccessEventFlowException::class
        );

        $this->expectExceptionMessage(
            'A análise manual mais recente não corresponde à decisão operacional atual.'
        );

        $this->repository()->prepare(
            eventId: $scenario['event']->id,
            operatorUserId: $scenario['operator']->id,
        );
    }

    public function test_it_rejects_a_missing_operator(): void
    {
        $scenario = $this->createScenario();

        $this->expectException(
            ReprocessAccessEventFlowException::class
        );

        $this->expectExceptionMessage(
            'O operador responsável não foi encontrado.'
        );

        $this->repository()->prepare(
            eventId: $scenario['event']->id,
            operatorUserId: 999999,
        );
    }

    public function test_it_rejects_an_operator_without_permission(): void
    {
        $scenario = $this->createScenario();

        $scenario['operator']->revokePermissionTo(
            'ReprocessFlow:AccessEventRecord'
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $this->expectException(
            ReprocessAccessEventFlowException::class
        );

        $this->expectExceptionMessage(
            'O operador não possui autorização para reprocessar este evento.'
        );

        $this->repository()->prepare(
            eventId: $scenario['event']->id,
            operatorUserId: $scenario['operator']->id,
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
            ReprocessAccessEventFlowException::class
        );

        $this->expectExceptionMessage(
            'O operador não possui autorização para reprocessar este evento.'
        );

        $this->repository()->prepare(
            eventId: $scenario['event']->id,
            operatorUserId: $scenario['operator']->id,
        );
    }

    private function repository(): ReprocessAccessEventFlowRepository
    {
        return app(
            ReprocessAccessEventFlowRepository::class
        );
    }

    /**
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     event: AccessEventRecord,
     *     decision: AccessEventOperationalDecisionRecord,
     *     operator: User
     * }
     */
    private function createScenario(
        AccessEventOperationalDecision $decision =
            AccessEventOperationalDecision::ManualReview,
    ): array {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO REPROCESSAMENTO CONTROLADO',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE REPROCESSAMENTO CONTROLADO LTDA',
                'display_name' => 'UNIDADE REPROCESSAMENTO CONTROLADO',
                'unit_code' => 'RPC-01',
            ]);

        $device = AccessDeviceRecord::query()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'code' => 'FAC-RPC-01',
                'name' => 'LEITOR REPROCESSAMENTO CONTROLADO',
                'provider' => 'simulator',
                'direction' => AccessDeviceDirection::Entry,
                'status' => AccessDeviceStatus::Active,
            ]);

        $event = AccessEventRecord::query()
            ->create([
                'access_device_id' => $device->id,
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'external_event_id' => 'controlled-reprocess-'.Str::uuid(),

                'event_type' => 'face_recognition',
                'direction' => AccessEventDirection::Entry,
                'occurred_at' => now(),
                'status' => AccessEventStatus::Processed,
                'received_at' => now(),
                'processed_at' => now(),
                'processing_attempts' => 1,
            ]);

        $decisionRecord =
            AccessEventOperationalDecisionRecord::query()
                ->create([
                    'access_event_id' => $event->id,
                    'tenant_id' => $tenant->id,
                    'organization_id' => $organization->id,
                    'visitor_id' => null,
                    'visit_id' => null,
                    'version' => 1,
                    'decision' => $decision,
                    'reason_code' => 'controlled_test',
                    'reason_message' => 'Decisão sintética para teste controlado.',

                    'automatic_execution_enabled' => false,

                    'decided_at' => now(),
                ]);

        $permission = Permission::findOrCreate(
            'ReprocessFlow:AccessEventRecord',
            'web'
        );

        $operator = User::factory()->create([
            'name' => 'OPERADOR REPROCESSAMENTO CONTROLADO',
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
            'decision' => $decisionRecord,
            'operator' => $operator,
        ];
    }

    /**
     * @param array{
     *     event: AccessEventRecord,
     *     decision: AccessEventOperationalDecisionRecord,
     *     operator: User
     * } $scenario
     */
    private function createReview(
        array $scenario,
        AccessEventManualReviewDisposition $disposition,
    ): AccessEventManualReviewRecord {
        return AccessEventManualReviewRecord::query()
            ->create([
                'access_event_id' => $scenario['event']->id,

                'operational_decision_id' => $scenario['decision']->id,

                'tenant_id' => $scenario['event']->tenant_id,

                'organization_id' => $scenario['event']->organization_id,

                'visitor_id' => null,
                'visit_id' => null,
                'idempotency_key' => (string) Str::uuid(),

                'operator_user_id' => $scenario['operator']->id,

                'operator_name' => $scenario['operator']->name,

                'decision_version' => $scenario['decision']->version,

                'decision_reason_code' => $scenario['decision']->reason_code,

                'decision_reason_message' => $scenario['decision']->reason_message,

                'disposition' => $disposition,

                'notes' => 'Análise sintética para validar o reprocessamento controlado.',

                'reviewed_at' => now(),
            ]);
    }
}
