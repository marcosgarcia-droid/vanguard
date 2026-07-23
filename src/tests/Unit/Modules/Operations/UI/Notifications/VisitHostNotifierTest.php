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

    public function test_host_notifications_store_structured_visit_metadata(): void
    {
        [
            'hostUser' => $hostUser,
            'visit' => $visit,
        ] = $this->createVisitContext();

        $notifier = app(
            VisitHostNotifier::class
        );

        $notifier->notifyScheduled($visit);

        $visit->forceFill([
            'arrived_at' => now(),
        ])->save();

        $notifier->notifyArrival($visit);

        $notifications = $hostUser->notifications()
            ->get();

        $this->assertCount(
            2,
            $notifications
        );

        foreach ($notifications as $notification) {
            $viewData = $notification->data[
                'viewData'
            ] ?? [];

            $this->assertSame(
                'visit_host_decision',
                $viewData['notification_kind']
                    ?? null
            );

            $this->assertSame(
                $visit->id,
                $viewData['visit_id']
                    ?? null
            );

            $this->assertArrayNotHasKey(
                'decision_status',
                $viewData
            );
        }
    }

    public function test_it_closes_new_and_legacy_decision_notifications_idempotently(): void
    {
        [
            'tenant' => $tenant,
            'organization' => $organization,
            'hostUser' => $hostUser,
            'host' => $host,
            'visit' => $visit,
        ] = $this->createVisitContext();

        $notifier = app(
            VisitHostNotifier::class
        );

        $notifier->notifyScheduled($visit);

        $visit->forceFill([
            'arrived_at' => now(),
        ])->save();

        $notifier->notifyArrival($visit);

        $originalNotifications = $hostUser
            ->notifications()
            ->oldest('created_at')
            ->get();

        $this->assertCount(
            2,
            $originalNotifications
        );

        $legacyNotification = $originalNotifications
            ->first();

        $currentNotification = $originalNotifications
            ->last();

        $legacyData = $legacyNotification->data;
        $legacyData['viewData'] = [];

        $legacyReadAt = now()
            ->subMinute()
            ->startOfSecond();

        $legacyNotification->forceFill([
            'data' => $legacyData,
            'read_at' => $legacyReadAt,
        ])->save();

        $otherVisitor = VisitorRecord::query()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'full_name' => 'OUTRA PESSOA VISITANTE',
                'status' => VisitorStatus::Active,
            ]);

        $otherVisit = VisitRecord::query()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'visitor_id' => $otherVisitor->id,
                'host_employee_id' => $host->id,
                'status' => VisitStatus::Scheduled,
                'purpose' => 'OUTRA VISITA NÃO ENCERRADA',
                'expected_start_at' => '2026-07-23 16:00:00',
                'expected_end_at' => '2026-07-23 17:00:00',
            ]);

        $notifier->notifyScheduled(
            $otherVisit
        );

        $visit->forceFill([
            'status' => VisitStatus::Authorized,
            'authorized_at' => now(),
        ])->save();

        $notifier->closeDecisionActions(
            $visit
        );

        $closedNotifications = $hostUser
            ->notifications()
            ->get()
            ->filter(
                fn ($notification): bool => str_contains(
                    json_encode(
                        $notification->data,
                        JSON_THROW_ON_ERROR
                    ),
                    $visit->id
                )
            )
            ->values();

        $this->assertCount(
            2,
            $closedNotifications
        );

        foreach (
            $closedNotifications as $notification
        ) {
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
                $visit->id,
                $data['viewData']['visit_id']
                    ?? null
            );

            $this->assertSame(
                VisitStatus::Authorized->value,
                $data['viewData'][
                    'decision_status'
                ] ?? null
            );

            $action = $data['actions'][0]
                ?? [];

            $url = urldecode(
                (string) (
                    $action['url']
                    ?? ''
                )
            );

            $this->assertStringContainsString(
                'tableAction=view',
                $url
            );

            $this->assertStringContainsString(
                'tableActionRecord='.$visit->id,
                $url
            );
        }

        $legacyNotification->refresh();
        $currentNotification->refresh();

        $this->assertSame(
            $legacyReadAt->format(
                'Y-m-d H:i:s'
            ),
            $legacyNotification->read_at
                ?->format('Y-m-d H:i:s')
        );

        $this->assertNull(
            $currentNotification->read_at
        );

        $otherNotification = $hostUser
            ->notifications()
            ->get()
            ->first(
                fn ($notification): bool => str_contains(
                    json_encode(
                        $notification->data,
                        JSON_THROW_ON_ERROR
                    ),
                    $otherVisit->id
                )
            );

        $this->assertNotNull(
            $otherNotification
        );

        $otherActionNames = collect(
            $otherNotification->data[
                'actions'
            ] ?? []
        )
            ->pluck('name')
            ->all();

        $this->assertContains(
            'authorizeHostVisit',
            $otherActionNames
        );

        $this->assertContains(
            'rejectHostVisit',
            $otherActionNames
        );

        $this->assertNotContains(
            'openVisit',
            $otherActionNames
        );

        $beforeSecondExecution = $closedNotifications
            ->mapWithKeys(
                fn ($notification): array => [
                    $notification->id => [
                        'data' => $notification->data,
                        'read_at' => $notification
                            ->read_at
                            ?->format('Y-m-d H:i:s'),
                    ],
                ]
            )
            ->all();

        $notifier->closeDecisionActions(
            $visit
        );

        $afterSecondExecution = $hostUser
            ->notifications()
            ->whereIn(
                'id',
                array_keys(
                    $beforeSecondExecution
                )
            )
            ->get()
            ->mapWithKeys(
                fn ($notification): array => [
                    $notification->id => [
                        'data' => $notification->data,
                        'read_at' => $notification
                            ->read_at
                            ?->format('Y-m-d H:i:s'),
                    ],
                ]
            )
            ->all();

        ksort($beforeSecondExecution);
        ksort($afterSecondExecution);

        $this->assertSame(
            $beforeSecondExecution,
            $afterSecondExecution
        );

        $this->assertDatabaseCount(
            'notifications',
            3
        );
    }

    public function test_cancellation_closes_existing_decisions_and_notifies_the_host_idempotently(): void
    {
        [
            'hostUser' => $hostUser,
            'unrelatedUser' => $unrelatedUser,
            'visit' => $visit,
        ] = $this->createVisitContext();

        $notifier = app(
            VisitHostNotifier::class
        );

        $notifier->notifyScheduled(
            $visit
        );

        $originalNotification = $hostUser
            ->notifications()
            ->sole();

        $originalReadAt = now()
            ->subMinute()
            ->startOfSecond();

        $originalNotification->forceFill([
            'read_at' => $originalReadAt,
        ])->save();

        $visit->forceFill([
            'status' => VisitStatus::Cancelled,
            'cancelled_by' => $unrelatedUser->id,
            'cancelled_at' => now(),
            'cancellation_reason' => 'O visitante informou que não comparecerá.',
        ])->save();

        $notifier->closeDecisionActions(
            $visit
        );

        $notifier->notifyCancelled(
            $visit,
            (int) $unrelatedUser->id
        );

        $notifications = $hostUser
            ->notifications()
            ->get();

        $this->assertCount(
            2,
            $notifications
        );

        $decisionNotification = $notifications
            ->first(
                fn ($notification): bool => (
                    $notification->data[
                        'viewData'
                    ][
                        'notification_kind'
                    ] ?? null
                ) === 'visit_host_decision'
            );

        $cancellationNotification = $notifications
            ->first(
                fn ($notification): bool => (
                    $notification->data[
                        'viewData'
                    ][
                        'notification_kind'
                    ] ?? null
                ) === 'visit_cancelled'
            );

        $this->assertNotNull(
            $decisionNotification
        );

        $this->assertNotNull(
            $cancellationNotification
        );

        $decisionData = $decisionNotification->data;
        $cancellationData = $cancellationNotification->data;

        $this->assertSame(
            ['openVisit'],
            collect(
                $decisionData['actions']
                    ?? []
            )
                ->pluck('name')
                ->all()
        );

        $this->assertSame(
            VisitStatus::Cancelled->value,
            $decisionData[
                'viewData'
            ][
                'decision_status'
            ] ?? null
        );

        $this->assertSame(
            $originalReadAt->format(
                'Y-m-d H:i:s'
            ),
            $decisionNotification->read_at
                ?->format('Y-m-d H:i:s')
        );

        $this->assertSame(
            'Visita cancelada',
            $cancellationData['title']
                ?? null
        );

        $this->assertSame(
            'danger',
            $cancellationData['status']
                ?? null
        );

        $this->assertSame(
            'visit_cancelled',
            $cancellationData[
                'viewData'
            ][
                'notification_kind'
            ] ?? null
        );

        $this->assertSame(
            $visit->id,
            $cancellationData[
                'viewData'
            ][
                'visit_id'
            ] ?? null
        );

        $this->assertSame(
            VisitStatus::Cancelled->value,
            $cancellationData[
                'viewData'
            ][
                'decision_status'
            ] ?? null
        );

        $this->assertSame(
            ['openVisit'],
            collect(
                $cancellationData['actions']
                    ?? []
            )
                ->pluck('name')
                ->all()
        );

        $body = (string) (
            $cancellationData['body']
                ?? ''
        );

        $this->assertStringContainsString(
            'PESSOA VISITANTE',
            $body
        );

        $this->assertStringContainsString(
            'UNIDADE ANFITRIÃO',
            $body
        );

        $this->assertStringContainsString(
            'O visitante informou que não comparecerá.',
            $body
        );

        $this->assertStringNotContainsString(
            'comparecerá..',
            $body
        );

        $this->assertNotificationDoesNotExistFor(
            $unrelatedUser
        );

        $beforeSecondExecution = $notifications
            ->mapWithKeys(
                fn ($notification): array => [
                    $notification->id => [
                        'data' => $notification->data,
                        'read_at' => $notification
                            ->read_at
                            ?->format(
                                'Y-m-d H:i:s'
                            ),
                    ],
                ]
            )
            ->all();

        $notifier->closeDecisionActions(
            $visit
        );

        $notifier->notifyCancelled(
            $visit,
            (int) $unrelatedUser->id
        );

        $afterSecondExecution = $hostUser
            ->notifications()
            ->get()
            ->mapWithKeys(
                fn ($notification): array => [
                    $notification->id => [
                        'data' => $notification->data,
                        'read_at' => $notification
                            ->read_at
                            ?->format(
                                'Y-m-d H:i:s'
                            ),
                    ],
                ]
            )
            ->all();

        ksort($beforeSecondExecution);
        ksort($afterSecondExecution);

        $this->assertSame(
            $beforeSecondExecution,
            $afterSecondExecution
        );

        $this->assertDatabaseCount(
            'notifications',
            2
        );
    }

    public function test_it_does_not_send_a_cancellation_notice_to_the_host_who_cancelled_the_visit(): void
    {
        [
            'hostUser' => $hostUser,
            'visit' => $visit,
        ] = $this->createVisitContext();

        $visit->forceFill([
            'status' => VisitStatus::Cancelled,
            'cancelled_by' => $hostUser->id,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Cancelamento realizado pelo próprio visitado',
        ])->save();

        app(VisitHostNotifier::class)
            ->notifyCancelled(
                $visit,
                (int) $hostUser->id
            );

        $this->assertNotificationDoesNotExistFor(
            $hostUser
        );

        $this->assertDatabaseCount(
            'notifications',
            0
        );
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
