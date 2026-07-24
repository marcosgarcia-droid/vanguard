<?php

namespace Tests\Unit\Modules\Operations\Application\Visits\ExpireVisit;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\Visits\ExpireVisit\ExpireVisitCommand;
use App\Modules\Operations\Application\Visits\ExpireVisit\ExpireVisitUseCase;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ExpireVisitUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private TenantRecord $tenant;

    private OrganizationRecord $organization;

    private VisitorRecord $visitor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = TenantRecord::query()
            ->create([
                'id' => (string) Str::uuid(),
                'name' => 'GRUPO EXPIRAÇÃO',
                'status' => 'active',
            ]);

        $this->organization = OrganizationRecord::query()
            ->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE EXPIRAÇÃO LTDA',
                'display_name' => 'UNIDADE EXPIRAÇÃO',
                'unit_code' => 'EXP-01',
            ]);

        $this->visitor = VisitorRecord::query()
            ->create([
                'tenant_id' => $this->tenant->id,
                'organization_id' => $this->organization->id,
                'full_name' => 'VISITANTE EXPIRAÇÃO',
                'status' => VisitorStatus::Active,
            ]);
    }

    public function test_it_expires_the_eligible_statuses_at_the_effective_deadline(): void
    {
        foreach (
            ExpireVisitUseCase::eligibleStatuses() as $status
        ) {
            $visit = $this->visit(
                status: $status,
                expectedStartAt: '2026-07-22 07:00:00',
                expectedEndAt: '2026-07-22 08:00:00',
            );

            $result = app(
                ExpireVisitUseCase::class
            )->execute(
                new ExpireVisitCommand(
                    visitId: $visit->id,
                    referenceAt: new DateTimeImmutable(
                        '2026-07-23 08:00:00'
                    ),
                    graceHours: 24,
                )
            );

            $this->assertSame(
                VisitStatus::Expired,
                $result->status
            );

            $this->assertSame(
                '2026-07-23 08:00:00',
                $result->expired_at?->format(
                    'Y-m-d H:i:s'
                )
            );

            $this->assertTrue(
                $result->wasChanged(
                    'expired_at'
                )
            );
        }
    }

    public function test_it_is_idempotent_after_the_first_expiration(): void
    {
        $visit = $this->visit(
            status: VisitStatus::Scheduled,
            expectedStartAt: '2026-07-22 07:00:00',
            expectedEndAt: '2026-07-22 08:00:00',
        );

        $firstResult = app(
            ExpireVisitUseCase::class
        )->execute(
            new ExpireVisitCommand(
                visitId: $visit->id,
                referenceAt: new DateTimeImmutable(
                    '2026-07-23 08:00:00'
                ),
                graceHours: 24,
            )
        );

        $this->assertTrue(
            $firstResult->wasChanged(
                'expired_at'
            )
        );

        $secondResult = app(
            ExpireVisitUseCase::class
        )->execute(
            new ExpireVisitCommand(
                visitId: $visit->id,
                referenceAt: new DateTimeImmutable(
                    '2026-07-24 08:00:00'
                ),
                graceHours: 24,
            )
        );

        $this->assertFalse(
            $secondResult->wasChanged(
                'expired_at'
            )
        );

        $this->assertSame(
            '2026-07-23 08:00:00',
            $secondResult->expired_at?->format(
                'Y-m-d H:i:s'
            )
        );
    }

    public function test_it_uses_the_start_time_when_the_end_time_is_missing(): void
    {
        $visit = $this->visit(
            status: VisitStatus::Scheduled,
            expectedStartAt: '2026-07-22 07:00:00',
            expectedEndAt: null,
        );

        $result = app(
            ExpireVisitUseCase::class
        )->execute(
            new ExpireVisitCommand(
                visitId: $visit->id,
                referenceAt: new DateTimeImmutable(
                    '2026-07-23 08:00:00'
                ),
                graceHours: 24,
            )
        );

        $this->assertSame(
            VisitStatus::Expired,
            $result->status
        );

        $this->assertSame(
            '2026-07-23 07:00:00',
            $result->expired_at?->format(
                'Y-m-d H:i:s'
            )
        );
    }

    public function test_it_preserves_visits_within_the_grace_period_or_outside_the_eligible_flow(): void
    {
        $withinGrace = $this->visit(
            status: VisitStatus::Scheduled,
            expectedStartAt: '2026-07-23 07:30:00',
            expectedEndAt: '2026-07-23 08:30:00',
        );

        $checkedIn = $this->visit(
            status: VisitStatus::Authorized,
            expectedStartAt: '2026-07-21 07:00:00',
            expectedEndAt: '2026-07-21 08:00:00',
            checkedInAt: '2026-07-21 07:30:00',
        );

        $protectedVisits = [
            $withinGrace,
            $checkedIn,
        ];

        foreach ([
            VisitStatus::Draft,
            VisitStatus::Rejected,
            VisitStatus::InProgress,
            VisitStatus::Completed,
            VisitStatus::Cancelled,
            VisitStatus::Expired,
        ] as $status) {
            $protectedVisits[] = $this->visit(
                status: $status,
                expectedStartAt: '2026-07-21 07:00:00',
                expectedEndAt: '2026-07-21 08:00:00',
            );
        }

        foreach ($protectedVisits as $visit) {
            $originalStatus = $visit->status;

            $result = app(
                ExpireVisitUseCase::class
            )->execute(
                new ExpireVisitCommand(
                    visitId: $visit->id,
                    referenceAt: new DateTimeImmutable(
                        '2026-07-24 08:00:00'
                    ),
                    graceHours: 24,
                )
            );

            $this->assertSame(
                $originalStatus,
                $result->status
            );

            $this->assertFalse(
                $result->wasChanged(
                    'expired_at'
                )
            );
        }
    }

    private function visit(
        VisitStatus $status,
        string $expectedStartAt,
        ?string $expectedEndAt,
        ?string $checkedInAt = null,
    ): VisitRecord {
        return VisitRecord::query()
            ->create([
                'tenant_id' => $this->tenant->id,
                'organization_id' => $this->organization->id,
                'visitor_id' => $this->visitor->id,
                'status' => $status,
                'purpose' => 'TESTE DE EXPIRAÇÃO '.Str::random(8),
                'expected_start_at' => $expectedStartAt,
                'expected_end_at' => $expectedEndAt,
                'checked_in_at' => $checkedInAt,
            ]);
    }
}
