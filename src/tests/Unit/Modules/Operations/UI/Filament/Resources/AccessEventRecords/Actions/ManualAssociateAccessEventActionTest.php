<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
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
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions\ManualAssociateAccessEventAction;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Tables\AccessEventRecordsTable;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

class ManualAssociateAccessEventActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_manual_association_action(): void
    {
        $action =
            ManualAssociateAccessEventAction::make();

        $this->assertInstanceOf(
            Action::class,
            $action
        );

        $this->assertSame(
            'manualAssociateAccessEvent',
            $action->getName()
        );
    }

    public function test_it_declares_the_scoped_modal_and_controlled_use_case(): void
    {
        $source = $this->sourceOf(
            ManualAssociateAccessEventAction::class
        );

        foreach ([
            "Select::make('visitor_id')",
            "Select::make('visit_id')",
            "Textarea::make('reason')",
            "Hidden::make('idempotency_key')",
            'VisitorStatus::Active->value',
            'VisitStatus::Authorized',
            'VisitStatus::InProgress',
            "'tenant_id'",
            "'organization_id'",
            "'visitor_id'",
            'AccessEventStatus::PendingAssociation',
            "Gate::authorize(\n                        'associateManually'",
            'ManualAssociateAccessEventUseCase::class',
            'ManualAssociateAccessEventCommand(',
            'operatorUserId: (int) $user->id',
            "->event(\n                'access_event_manually_associated'",
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }
    }

    public function test_it_does_not_execute_operational_or_device_commands(): void
    {
        $source = $this->sourceOf(
            ManualAssociateAccessEventAction::class
        );

        foreach ([
            'Http::',
            'raw_payload',
            'CheckInVisitUseCase',
            'CheckOutVisitUseCase',
            'ContinueAccessEventFlowUseCase',
            'DecideAccessEventUseCase',
            'RegisterAccessEventOperationalExecutionUseCase',
            'ExecuteAccessEventOperationalExecutionUseCase',
            'openDoor',
            'unlock',
            'relay',
            'setConfig',
        ] as $unexpected) {
            $this->assertStringNotContainsString(
                $unexpected,
                $source
            );
        }
    }

    public function test_the_event_table_exposes_the_manual_association_action(): void
    {
        $source = $this->sourceOf(
            AccessEventRecordsTable::class
        );

        $this->assertStringContainsString(
            'ManualAssociateAccessEventAction::make()',
            $source
        );
    }

    public function test_visitor_options_include_only_active_visitors_from_the_event_unit(): void
    {
        $scenario = $this->createScenario(
            AccessEventDirection::Entry
        );

        $allowed = $this->createVisitor(
            $scenario['tenant'],
            $scenario['organization'],
            'VISITANTE PERMITIDO',
            VisitorStatus::Active,
        );

        $inactive = $this->createVisitor(
            $scenario['tenant'],
            $scenario['organization'],
            'VISITANTE INATIVO',
            VisitorStatus::Inactive,
        );

        $otherOrganization =
            $this->createOrganization(
                $scenario['tenant'],
                'OUTRA UNIDADE',
                'OUT-01',
            );

        $otherUnit = $this->createVisitor(
            $scenario['tenant'],
            $otherOrganization,
            'VISITANTE OUTRA UNIDADE',
            VisitorStatus::Active,
        );

        $options =
            ManualAssociateAccessEventAction::visitorOptions(
                $scenario['event']
            );

        $this->assertArrayHasKey(
            $allowed->id,
            $options
        );

        $this->assertSame(
            'VISITANTE PERMITIDO',
            $options[$allowed->id]
        );

        $this->assertArrayNotHasKey(
            $inactive->id,
            $options
        );

        $this->assertArrayNotHasKey(
            $otherUnit->id,
            $options
        );
    }

    public function test_entry_visit_options_include_only_authorized_visits_for_the_selected_visitor(): void
    {
        $scenario = $this->createScenario(
            AccessEventDirection::Entry
        );

        $visitor = $this->createVisitor(
            $scenario['tenant'],
            $scenario['organization'],
            'VISITANTE ENTRADA',
            VisitorStatus::Active,
        );

        $otherVisitor = $this->createVisitor(
            $scenario['tenant'],
            $scenario['organization'],
            'OUTRO VISITANTE',
            VisitorStatus::Active,
        );

        $authorized = $this->createVisit(
            $visitor,
            $scenario['organization'],
            VisitStatus::Authorized,
            'VISITA AUTORIZADA',
        );

        $scheduled = $this->createVisit(
            $visitor,
            $scenario['organization'],
            VisitStatus::Scheduled,
            'VISITA AGENDADA',
        );

        $otherVisitorVisit = $this->createVisit(
            $otherVisitor,
            $scenario['organization'],
            VisitStatus::Authorized,
            'VISITA DE OUTRA PESSOA',
        );

        $options =
            ManualAssociateAccessEventAction::visitOptions(
                $scenario['event'],
                $visitor->id
            );

        $this->assertArrayHasKey(
            $authorized->id,
            $options
        );

        $this->assertStringContainsString(
            'VISITA AUTORIZADA',
            $options[$authorized->id]
        );

        $this->assertArrayNotHasKey(
            $scheduled->id,
            $options
        );

        $this->assertArrayNotHasKey(
            $otherVisitorVisit->id,
            $options
        );
    }

    public function test_exit_visit_options_include_only_in_progress_visits(): void
    {
        $scenario = $this->createScenario(
            AccessEventDirection::Exit
        );

        $visitor = $this->createVisitor(
            $scenario['tenant'],
            $scenario['organization'],
            'VISITANTE SAÍDA',
            VisitorStatus::Active,
        );

        $inProgress = $this->createVisit(
            $visitor,
            $scenario['organization'],
            VisitStatus::InProgress,
            'VISITA EM ANDAMENTO',
        );

        $authorized = $this->createVisit(
            $visitor,
            $scenario['organization'],
            VisitStatus::Authorized,
            'VISITA APENAS AUTORIZADA',
        );

        $options =
            ManualAssociateAccessEventAction::visitOptions(
                $scenario['event'],
                $visitor->id
            );

        $this->assertArrayHasKey(
            $inProgress->id,
            $options
        );

        $this->assertArrayNotHasKey(
            $authorized->id,
            $options
        );
    }

    /**
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     event: AccessEventRecord
     * }
     */
    private function createScenario(
        AccessEventDirection $direction
    ): array {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO ACTION ASSOCIAÇÃO',
            'status' => 'active',
        ]);

        $organization =
            $this->createOrganization(
                $tenant,
                'UNIDADE ACTION ASSOCIAÇÃO',
                'ACT-01',
            );

        $device = AccessDeviceRecord::query()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'code' => 'FAC-ACT-01',
                'name' => 'LEITOR ACTION',
                'provider' => 'simulator',
                'direction' => match ($direction) {
                    AccessEventDirection::Entry => AccessDeviceDirection::Entry,

                    AccessEventDirection::Exit => AccessDeviceDirection::Exit,
                },
                'status' => AccessDeviceStatus::Active,
            ]);

        $event = AccessEventRecord::query()
            ->create([
                'access_device_id' => $device->id,
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'external_event_id' => 'manual-action-'.Str::uuid(),
                'event_type' => 'face_recognition',
                'direction' => $direction,
                'occurred_at' => now(),
                'status' => AccessEventStatus::PendingAssociation,
                'received_at' => now(),
            ]);

        return [
            'tenant' => $tenant,
            'organization' => $organization,
            'event' => $event,
        ];
    }

    private function createOrganization(
        TenantRecord $tenant,
        string $name,
        string $code,
    ): OrganizationRecord {
        return OrganizationRecord::query()
            ->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => "{$name} LTDA",
                'display_name' => $name,
                'unit_code' => $code,
            ]);
    }

    private function createVisitor(
        TenantRecord $tenant,
        OrganizationRecord $organization,
        string $name,
        VisitorStatus $status,
    ): VisitorRecord {
        return VisitorRecord::query()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'full_name' => $name,
                'status' => $status,
            ]);
    }

    private function createVisit(
        VisitorRecord $visitor,
        OrganizationRecord $organization,
        VisitStatus $status,
        string $purpose,
    ): VisitRecord {
        return VisitRecord::query()
            ->create([
                'tenant_id' => $organization->tenant_id,
                'organization_id' => $organization->id,
                'visitor_id' => $visitor->id,
                'status' => $status,
                'purpose' => $purpose,
                'expected_start_at' => now(),
                'expected_end_at' => now()->addHour(),
            ]);
    }

    private function sourceOf(
        string $class
    ): string {
        $filename = (
            new ReflectionClass($class)
        )->getFileName();

        $this->assertIsString($filename);

        $source = file_get_contents($filename);

        $this->assertIsString($source);

        return $source;
    }
}
