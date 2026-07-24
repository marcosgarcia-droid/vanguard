<?php

namespace Tests\Feature\Modules\Operations\Visits;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\UI\Notifications\VisitHostNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ExpireOverdueVisitsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            '2026-07-24 08:00:00'
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_does_not_execute_while_the_automation_is_disabled(): void
    {
        $context = $this->context();

        $visit = $this->visit(
            $context,
            VisitStatus::Scheduled,
            '2026-07-23 06:00:00',
            '2026-07-23 07:00:00',
        );

        config()->set(
            'vanguard.operations.visits.expiration.enabled',
            false
        );

        $this->artisan(
            'vanguard:visits:expire'
        )
            ->expectsOutputToContain(
                'A expiração automática de visitas está desativada.'
            )
            ->assertSuccessful();

        $this->assertSame(
            VisitStatus::Scheduled,
            $visit->fresh()->status
        );
    }

    public function test_dry_run_reports_candidates_without_changing_visits(): void
    {
        $context = $this->context();

        $visit = $this->visit(
            $context,
            VisitStatus::Scheduled,
            '2026-07-23 06:00:00',
            '2026-07-23 07:00:00',
        );

        config()->set(
            'vanguard.operations.visits.expiration.enabled',
            false
        );

        config()->set(
            'vanguard.operations.visits.expiration.grace_hours',
            24
        );

        $this->artisan(
            'vanguard:visits:expire',
            [
                '--dry-run' => true,
            ]
        )
            ->expectsOutputToContain(
                'Simulação concluída: 1 visita(s) elegível(is) para expiração.'
            )
            ->assertSuccessful();

        $this->assertSame(
            VisitStatus::Scheduled,
            $visit->fresh()->status
        );

        $this->assertNull(
            $visit->fresh()->expired_at
        );
    }

    public function test_it_expires_only_overdue_visits_and_closes_host_decisions(): void
    {
        $context = $this->context();

        $overdue = $this->visit(
            $context,
            VisitStatus::Scheduled,
            '2026-07-23 06:00:00',
            '2026-07-23 07:00:00',
        );

        $withinGrace = $this->visit(
            $context,
            VisitStatus::Scheduled,
            '2026-07-24 06:00:00',
            '2026-07-24 07:00:00',
        );

        $inProgress = $this->visit(
            $context,
            VisitStatus::InProgress,
            '2026-07-22 06:00:00',
            '2026-07-22 07:00:00',
            '2026-07-22 06:30:00',
        );

        app(VisitHostNotifier::class)
            ->notifyScheduled(
                $overdue->fresh([
                    'visitor',
                    'organization',
                    'hostEmployee.user',
                ])
            );

        config()->set(
            'vanguard.operations.visits.expiration.enabled',
            true
        );

        config()->set(
            'vanguard.operations.visits.expiration.grace_hours',
            24
        );

        config()->set(
            'vanguard.operations.visits.expiration.batch_size',
            100
        );

        $this->artisan(
            'vanguard:visits:expire'
        )
            ->expectsOutputToContain(
                '1 visita(s) expirada(s).'
            )
            ->assertSuccessful();

        $overdue->refresh();
        $withinGrace->refresh();
        $inProgress->refresh();

        $this->assertSame(
            VisitStatus::Expired,
            $overdue->status
        );

        $this->assertSame(
            '2026-07-24 07:00:00',
            $overdue->expired_at?->format(
                'Y-m-d H:i:s'
            )
        );

        $this->assertSame(
            VisitStatus::Scheduled,
            $withinGrace->status
        );

        $this->assertSame(
            VisitStatus::InProgress,
            $inProgress->status
        );

        $notification = $context[
            'hostUser'
        ]
            ->notifications()
            ->sole();

        $data = $notification->data;

        $this->assertSame(
            ['openVisit'],
            collect(
                $data['actions'] ?? []
            )
                ->pluck('name')
                ->all()
        );

        $this->assertSame(
            VisitStatus::Expired->value,
            $data[
                'viewData'
            ][
                'decision_status'
            ] ?? null
        );

        $this->assertDatabaseCount(
            'notifications',
            1
        );
    }

    public function test_it_respects_the_configured_batch_size(): void
    {
        $context = $this->context();

        $this->visit(
            $context,
            VisitStatus::Scheduled,
            '2026-07-22 04:00:00',
            '2026-07-22 05:00:00',
        );

        $this->visit(
            $context,
            VisitStatus::Scheduled,
            '2026-07-22 05:00:00',
            '2026-07-22 06:00:00',
        );

        config()->set(
            'vanguard.operations.visits.expiration.enabled',
            true
        );

        config()->set(
            'vanguard.operations.visits.expiration.grace_hours',
            24
        );

        config()->set(
            'vanguard.operations.visits.expiration.batch_size',
            1
        );

        $this->artisan(
            'vanguard:visits:expire'
        )
            ->expectsOutputToContain(
                '1 visita(s) expirada(s).'
            )
            ->assertSuccessful();

        $this->assertSame(
            1,
            VisitRecord::query()
                ->where(
                    'status',
                    VisitStatus::Expired
                )
                ->count()
        );

        $this->assertSame(
            1,
            VisitRecord::query()
                ->where(
                    'status',
                    VisitStatus::Scheduled
                )
                ->count()
        );
    }

    public function test_notification_synchronization_is_best_effort_after_the_expiration_is_persisted(): void
    {
        $source = file_get_contents(
            app_path(
                'Console/Commands/ExpireOverdueVisitsCommand.php'
            )
        );

        $this->assertIsString($source);

        $expirationPosition = strpos(
            $source,
            '$expireVisit->execute('
        );

        $notificationPosition = strpos(
            $source,
            '->closeDecisionActions(',
            $expirationPosition
        );

        $this->assertIsInt(
            $expirationPosition
        );

        $this->assertIsInt(
            $notificationPosition
        );

        $this->assertGreaterThan(
            $expirationPosition,
            $notificationPosition
        );

        $this->assertStringContainsString(
            'catch (',
            $source
        );

        $this->assertStringContainsString(
            'Throwable $notificationException',
            $source
        );

        $this->assertStringContainsString(
            '$notificationFailures++;',
            $source
        );

        $this->assertStringContainsString(
            'foi expirada, mas as ações pendentes da notificação do visitado não puderam ser encerradas',
            $source
        );

        $this->assertStringNotContainsString(
            'DB::transaction(',
            $source
        );
    }

    public function test_scheduler_and_list_expose_the_safe_expiration_flow(): void
    {
        $bootstrap = file_get_contents(
            base_path('bootstrap/app.php')
        );

        $list = file_get_contents(
            app_path(
                'Modules/Operations/UI/Filament/Resources/VisitRecords/Pages/ListVisitRecords.php'
            )
        );

        $this->assertIsString($bootstrap);
        $this->assertIsString($list);

        foreach ([
            "command('vanguard:visits:expire')",
            '->hourlyAt(10)',
            '->onOneServer()',
            '->withoutOverlapping(55)',
            'vanguard.operations.visits.expiration.enabled',
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $bootstrap
            );
        }

        foreach ([
            "'expired' => Tab::make('Expiradas')",
            'VisitStatus::Expired->value',
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $list
            );
        }
    }

    /**
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     hostUser: User,
     *     host: EmployeeRecord,
     *     visitor: VisitorRecord
     * }
     */
    private function context(): array
    {
        $tenant = TenantRecord::query()
            ->create([
                'id' => (string) Str::uuid(),
                'name' => 'GRUPO COMMAND EXPIRAÇÃO',
                'status' => 'active',
            ]);

        $organization = OrganizationRecord::query()
            ->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE COMMAND EXPIRAÇÃO LTDA',
                'display_name' => 'UNIDADE COMMAND EXPIRAÇÃO',
                'unit_code' => 'CEX-01',
            ]);

        $hostUser = User::factory()
            ->create([
                'name' => 'USUÁRIO VISITADO EXPIRAÇÃO',
            ]);

        $host = EmployeeRecord::query()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'user_id' => $hostUser->id,
                'full_name' => 'FUNCIONÁRIO VISITADO EXPIRAÇÃO',
                'employment_type' => 'employee',
                'status' => 'active',
            ]);

        $visitor = VisitorRecord::query()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'full_name' => 'VISITANTE COMMAND EXPIRAÇÃO',
                'status' => VisitorStatus::Active,
            ]);

        return compact(
            'tenant',
            'organization',
            'hostUser',
            'host',
            'visitor'
        );
    }

    /**
     * @param  array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     hostUser: User,
     *     host: EmployeeRecord,
     *     visitor: VisitorRecord
     * }  $context
     */
    private function visit(
        array $context,
        VisitStatus $status,
        string $expectedStartAt,
        ?string $expectedEndAt,
        ?string $checkedInAt = null,
    ): VisitRecord {
        return VisitRecord::query()
            ->create([
                'tenant_id' => $context['tenant']->id,
                'organization_id' => $context['organization']->id,
                'visitor_id' => $context['visitor']->id,
                'host_employee_id' => $context['host']->id,
                'status' => $status,
                'purpose' => 'TESTE COMMAND EXPIRAÇÃO '
                    .Str::random(8),
                'expected_start_at' => $expectedStartAt,
                'expected_end_at' => $expectedEndAt,
                'checked_in_at' => $checkedInAt,
            ]);
    }
}
