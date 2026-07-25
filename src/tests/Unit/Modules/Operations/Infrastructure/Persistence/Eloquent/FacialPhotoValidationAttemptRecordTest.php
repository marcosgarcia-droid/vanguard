<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoValidationAttemptRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class FacialPhotoValidationAttemptRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_preserves_an_immutable_facial_validation_ledger(): void
    {
        $context = $this->createContext();

        $attempt = $this->createAttempt(
            context: $context,
            decision: FacialPhotoValidationDecision::Approved,
            faceCount: 1,
            metrics: [
                'confidence' => 0.99,
                'centered' => true,
            ],
            issues: [],
            statusAfter: FacialPhotoStatus::Approved,
        );

        $loaded =
            FacialPhotoValidationAttemptRecord::query()
                ->with([
                    'facialPhoto',
                    'tenant',
                    'organization',
                    'operatorUser',
                ])
                ->findOrFail($attempt->id);

        $this->assertNotEmpty($loaded->id);

        $this->assertSame(
            1,
            $loaded->attempt_number
        );

        $this->assertSame(
            FacialPhotoValidationDecision::Approved,
            $loaded->decision
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $loaded->status_before
        );

        $this->assertSame(
            FacialPhotoStatus::Approved,
            $loaded->status_after
        );

        $this->assertSame(
            1,
            $loaded->face_count
        );

        $this->assertSame(
            [
                'confidence' => 0.99,
                'centered' => true,
            ],
            $loaded->metrics
        );

        $this->assertSame(
            [],
            $loaded->issues
        );

        $this->assertTrue(
            $loaded->facialPhoto->is(
                $context['photo']
            )
        );

        $this->assertTrue(
            $loaded->tenant->is(
                $context['tenant']
            )
        );

        $this->assertTrue(
            $loaded->organization->is(
                $context['organization']
            )
        );

        $this->assertTrue(
            $loaded->operatorUser->is(
                $context['operator']
            )
        );

        $this->assertTrue(
            $context['photo']
                ->validationAttempts()
                ->whereKey($attempt->id)
                ->exists()
        );

        $this->assertSame(
            $attempt->id,
            $context['photo']
                ->latestValidationAttempt()
                ->first()
                ?->id
        );

        $serialized = $loaded->toArray();

        $this->assertArrayNotHasKey(
            'metrics',
            $serialized
        );

        $this->assertArrayNotHasKey(
            'issues',
            $serialized
        );
    }

    public function test_it_preserves_multiple_attempts_and_exposes_the_latest(): void
    {
        $context = $this->createContext();

        $first = $this->createAttempt(
            context: $context,
            attemptNumber: 1,
            decision: FacialPhotoValidationDecision::Inconclusive,
            faceCount: 0,
            metrics: [
                'available' => false,
            ],
            issues: [
                FacialPhotoValidationIssue::ValidatorUnavailable
                    ->value,
            ],
            statusAfter: FacialPhotoStatus::PendingValidation,
        );

        $second = $this->createAttempt(
            context: $context,
            attemptNumber: 2,
            decision: FacialPhotoValidationDecision::Approved,
            faceCount: 1,
            metrics: [
                'confidence' => 0.98,
            ],
            issues: [],
            statusAfter: FacialPhotoStatus::Approved,
        );

        $this->assertSame(
            2,
            $context['photo']
                ->validationAttempts()
                ->count()
        );

        $this->assertSame(
            $second->id,
            $context['photo']
                ->latestValidationAttempt()
                ->first()
                ?->id
        );

        $this->assertNotSame(
            $first->id,
            $second->id
        );

        /*
         * O C4 registra somente o ledger. A mudança efetiva
         * da situação da foto será implementada no C5.
         */
        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $context['photo']
                ->refresh()
                ->status
        );
    }

    public function test_it_allows_an_automatic_attempt_without_operator(): void
    {
        $context = $this->createContext();

        $attempt = $this->createAttempt(
            context: $context,
            decision: FacialPhotoValidationDecision::Inconclusive,
            faceCount: 0,
            metrics: [
                'response_valid' => false,
            ],
            issues: [
                FacialPhotoValidationIssue::InvalidValidatorResponse
                    ->value,
            ],
            statusAfter: FacialPhotoStatus::PendingValidation,
            withOperator: false,
        );

        $this->assertNull(
            $attempt->operator_user_id
        );

        $this->assertNull(
            $attempt->operator_name
        );

        $this->assertNull(
            $attempt->operatorUser
        );
    }

    public function test_it_prevents_deleting_the_parent_photo_while_the_ledger_exists(): void
    {
        $context = $this->createContext();

        $this->createAttempt(
            $context
        );

        $this->expectException(
            QueryException::class
        );

        $context['photo']->delete();
    }

    public function test_it_rejects_updates_and_deletions(): void
    {
        $attempt = $this->createAttempt(
            $this->createContext()
        );

        try {
            $attempt
                ->forceFill([
                    'validator_version' => 'altered-version',
                ])
                ->save();

            $this->fail(
                'A atualização do ledger deveria ter sido bloqueada.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'As tentativas de validação facial são registros imutáveis.',
                $exception->getMessage()
            );
        }

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'As tentativas de validação facial não podem ser excluídas.'
        );

        $attempt->delete();
    }

    public function test_attempt_number_is_unique_per_facial_photo(): void
    {
        $context = $this->createContext();

        $this->createAttempt(
            context: $context,
            attemptNumber: 1,
        );

        $this->expectException(
            QueryException::class
        );

        $this->createAttempt(
            context: $context,
            attemptNumber: 1,
        );
    }

    /**
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     visitor: VisitorRecord,
     *     operator: User,
     *     photo: FacialPhotoRecord
     * }
     */
    private function createContext(): array
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO FACIAL SINTÉTICO',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE FACIAL SINTÉTICA LTDA',
                'display_name' => 'UNIDADE FACIAL SINTÉTICA',
                'unit_code' => 'FAC-01',
            ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE FACIAL SINTÉTICO',
            'status' => VisitorStatus::Active,
        ]);

        $operator = User::factory()->create([
            'name' => 'OPERADOR FACIAL SINTÉTICO',
        ]);

        $photo =
            $visitor
                ->facialPhotos()
                ->create([
                    'tenant_id' => $tenant->id,
                    'organization_id' => $organization->id,
                    'created_by' => $operator->id,
                    'source' => FacialPhotoSource::cases()[0],
                    'status' => FacialPhotoStatus::PendingValidation,
                    'captured_at' => '2026-07-25 14:00:00',
                ]);

        return [
            'tenant' => $tenant,
            'organization' => $organization,
            'visitor' => $visitor,
            'operator' => $operator,
            'photo' => $photo,
        ];
    }

    /**
     * @param  array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     visitor: VisitorRecord,
     *     operator: User,
     *     photo: FacialPhotoRecord
     * }  $context
     * @param  array<string, bool|int|float|string|null>  $metrics
     * @param  list<string>  $issues
     */
    private function createAttempt(
        array $context,
        int $attemptNumber = 1,
        FacialPhotoValidationDecision $decision =
            FacialPhotoValidationDecision::Inconclusive,
        int $faceCount = 0,
        array $metrics = [
            'available' => false,
        ],
        array $issues = [
            'validator_unavailable',
        ],
        FacialPhotoStatus $statusAfter =
            FacialPhotoStatus::PendingValidation,
        bool $withOperator = true,
    ): FacialPhotoValidationAttemptRecord {
        return FacialPhotoValidationAttemptRecord::query()
            ->create([
                'facial_photo_id' => $context['photo']->id,
                'tenant_id' => $context['tenant']->id,
                'organization_id' => $context['organization']->id,
                'operator_user_id' => $withOperator
                    ? $context['operator']->id
                    : null,
                'operator_name' => $withOperator
                    ? $context['operator']->name
                    : null,
                'attempt_number' => $attemptNumber,
                'validator' => 'simulated-facial-validator',
                'validator_version' => 'synthetic-v1',
                'decision' => $decision,
                'face_count' => $faceCount,
                'metrics' => $metrics,
                'issues' => $issues,
                'status_before' => FacialPhotoStatus::PendingValidation,
                'status_after' => $statusAfter,
                'validated_at' => now()->addSeconds(
                    $attemptNumber
                ),
            ]);
    }
}
