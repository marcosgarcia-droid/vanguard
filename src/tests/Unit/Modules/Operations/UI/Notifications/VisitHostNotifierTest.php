<?php

namespace Tests\Unit\Modules\Operations\UI\Notifications;

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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisitHostNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_notifies_the_linked_host_when_a_visit_is_scheduled(): void
    {
        [
            'hostUser' => $hostUser,
            'unrelatedUser' => $unrelatedUser,
            'visit' => $visit,
        ] = $this->createVisitContext();

        app(VisitHostNotifier::class)
            ->notifyScheduled($visit);

        $this->assertDatabaseCount(
            'notifications',
            1
        );

        $this->assertNotificationExistsFor(
            $hostUser,
            [
                'Nova visita agendada',
                'PESSOA VISITANTE',
                '22/07/2026 às 14:30',
                'UNIDADE ANFITRIÃO',
                'REUNIÃO DE ALINHAMENTO',
                'Autorizar meu visitante',
                'Não autorizar meu visitante',
                'Abrir visitas',
            ]
        );

        $this->assertNotificationDoesNotExistFor(
            $unrelatedUser
        );
    }

    public function test_it_notifies_the_linked_host_when_the_visitor_arrives(): void
    {
        [
            'hostUser' => $hostUser,
            'visit' => $visit,
        ] = $this->createVisitContext();

        $visit->forceFill([
            'arrived_at' => now(),
        ]);

        app(VisitHostNotifier::class)
            ->notifyArrival($visit);

        $this->assertDatabaseCount(
            'notifications',
            1
        );

        $this->assertNotificationExistsFor(
            $hostUser,
            [
                'Seu visitante chegou',
                'PESSOA VISITANTE',
                'chegou à portaria',
                'UNIDADE ANFITRIÃO',
                'REUNIÃO DE ALINHAMENTO',
                'Autorizar meu visitante',
                'Não autorizar meu visitante',
                'Abrir visitas',
            ]
        );
    }

    public function test_arrival_notification_opens_host_decision_modals_safely(): void
    {
        [
            'hostUser' => $hostUser,
            'visit' => $visit,
        ] = $this->createVisitContext();

        $visit->forceFill([
            'arrived_at' => now(),
        ])->save();

        app(VisitHostNotifier::class)
            ->notifyArrival($visit);

        $notification = DB::table('notifications')
            ->where(
                'notifiable_type',
                User::class
            )
            ->where(
                'notifiable_id',
                $hostUser->id
            )
            ->sole();

        $data = json_decode(
            (string) $notification->data,
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $actions = collect(
            $data['actions'] ?? []
        )->keyBy('name');

        $authorizeAction = $actions->get(
            'authorizeHostVisit'
        );

        $rejectAction = $actions->get(
            'rejectHostVisit'
        );

        $this->assertIsArray($authorizeAction);
        $this->assertIsArray($rejectAction);

        $this->assertSame(
            'Autorizar meu visitante',
            $authorizeAction['label'] ?? null
        );

        $this->assertSame(
            'Não autorizar meu visitante',
            $rejectAction['label'] ?? null
        );

        $authorizeUrl = urldecode(
            (string) ($authorizeAction['url'] ?? '')
        );

        $rejectUrl = urldecode(
            (string) ($rejectAction['url'] ?? '')
        );

        $this->assertStringContainsString(
            'tableAction=authorizeHostVisit',
            $authorizeUrl
        );

        $this->assertStringContainsString(
            'tableAction=rejectHostVisit',
            $rejectUrl
        );

        foreach ([
            $authorizeUrl,
            $rejectUrl,
        ] as $url) {
            $this->assertStringContainsString(
                'tableActionRecord='.$visit->id,
                $url
            );
        }

        foreach ([
            $authorizeAction,
            $rejectAction,
        ] as $action) {
            $this->assertTrue(
                (bool) (
                    $action['shouldMarkAsRead']
                    ?? false
                )
            );

            $this->assertFalse(
                (bool) (
                    $action['shouldPostToUrl']
                    ?? true
                )
            );
        }

        $visit->refresh();

        $this->assertSame(
            VisitStatus::Scheduled,
            $visit->status
        );

        $this->assertNull($visit->authorized_at);
        $this->assertNull($visit->rejected_at);
    }

    public function test_it_does_not_notify_when_the_host_has_no_linked_user(): void
    {
        [
            'host' => $host,
            'visit' => $visit,
        ] = $this->createVisitContext();

        $host->forceFill([
            'user_id' => null,
        ])->save();

        app(VisitHostNotifier::class)
            ->notifyScheduled($visit);

        app(VisitHostNotifier::class)
            ->notifyArrival($visit);

        $this->assertDatabaseCount(
            'notifications',
            0
        );
    }

    public function test_it_does_not_notify_an_inactive_host(): void
    {
        [
            'host' => $host,
            'visit' => $visit,
        ] = $this->createVisitContext();

        $host->forceFill([
            'status' => 'inactive',
        ])->save();

        app(VisitHostNotifier::class)
            ->notifyScheduled($visit);

        $this->assertDatabaseCount(
            'notifications',
            0
        );
    }

    public function test_the_create_action_notifies_only_after_the_visit_is_persisted(): void
    {
        $source = file_get_contents(
            app_path(
                'Modules/Operations/UI/Filament/Resources/VisitRecords/Pages/ListVisitRecords.php'
            )
        );

        $this->assertIsString($source);

        foreach ([
            '->after(function (VisitRecord $record): void {',
            'VisitHostNotifier::class',
            'notifyScheduled($record)',
            'report($exception)',
            'Visita agendada, mas o aviso ao visitado não foi enviado',
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }

        $usingPosition = strpos(
            $source,
            '->using('
        );

        $afterPosition = strpos(
            $source,
            '->after(function (VisitRecord $record): void {'
        );

        $this->assertIsInt($usingPosition);
        $this->assertIsInt($afterPosition);
        $this->assertGreaterThan(
            $usingPosition,
            $afterPosition
        );
    }

    /**
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     hostUser: User,
     *     unrelatedUser: User,
     *     host: EmployeeRecord,
     *     visitor: VisitorRecord,
     *     visit: VisitRecord
     * }
     */
    private function createVisitContext(): array
    {
        $tenant = TenantRecord::query()->create([
            'name' => 'GRUPO ANFITRIÃO',
            'status' => 'active',
        ]);

        $organization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'UNIDADE ANFITRIÃO LTDA',
            'display_name' => 'UNIDADE ANFITRIÃO',
        ]);

        $hostUser = User::factory()->create([
            'name' => 'USUÁRIO ANFITRIÃO',
        ]);

        $unrelatedUser = User::factory()->create([
            'name' => 'USUÁRIO NÃO RELACIONADO',
        ]);

        $host = EmployeeRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'user_id' => $hostUser->id,
            'full_name' => 'FUNCIONÁRIO ANFITRIÃO',
            'employment_type' => 'employee',
            'status' => 'active',
        ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'PESSOA VISITANTE',
            'status' => VisitorStatus::Active,
        ]);

        $visit = VisitRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'host_employee_id' => $host->id,
            'status' => VisitStatus::Scheduled,
            'purpose' => 'REUNIÃO DE ALINHAMENTO',
            'expected_start_at' => '2026-07-22 14:30:00',
            'expected_end_at' => '2026-07-22 15:30:00',
        ]);

        return compact(
            'tenant',
            'organization',
            'hostUser',
            'unrelatedUser',
            'host',
            'visitor',
            'visit'
        );
    }

    /**
     * @param  array<int, string>  $expectedFragments
     */
    private function assertNotificationExistsFor(
        User $user,
        array $expectedFragments
    ): void {
        $notification = DB::table('notifications')
            ->where(
                'notifiable_type',
                User::class
            )
            ->where(
                'notifiable_id',
                $user->id
            )
            ->first();

        $this->assertNotNull($notification);

        $decodedData = json_decode(
            (string) $notification->data,
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $searchableData = json_encode(
            $decodedData,
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
        );

        foreach ($expectedFragments as $fragment) {
            $this->assertStringContainsString(
                $fragment,
                $searchableData
            );
        }
    }

    private function assertNotificationDoesNotExistFor(
        User $user
    ): void {
        $this->assertFalse(
            DB::table('notifications')
                ->where(
                    'notifiable_type',
                    User::class
                )
                ->where(
                    'notifiable_id',
                    $user->id
                )
                ->exists()
        );
    }
}
