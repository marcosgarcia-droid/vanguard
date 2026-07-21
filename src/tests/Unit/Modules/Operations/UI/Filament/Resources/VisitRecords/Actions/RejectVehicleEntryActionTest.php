<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions;

use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitVehicleAuthorizationRequestRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitVehicleRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\RejectVehicleEntryAction;
use Filament\Actions\Action;
use ReflectionClass;
use Tests\TestCase;

class RejectVehicleEntryActionTest extends TestCase
{
    public function test_it_creates_the_reject_vehicle_entry_action(): void
    {
        $action = RejectVehicleEntryAction::make();

        $this->assertInstanceOf(
            Action::class,
            $action
        );

        $this->assertSame(
            'rejectVehicleEntry',
            $action->getName()
        );
    }

    public function test_it_accepts_only_eligible_visits_with_a_pending_request(): void
    {
        foreach ([
            VisitStatus::Scheduled,
            VisitStatus::PendingAuthorization,
            VisitStatus::Authorized,
        ] as $status) {
            $record = $this->visitWithVehicle(
                status: $status,
                pendingRequest: new VisitVehicleAuthorizationRequestRecord
            );

            $this->assertTrue(
                RejectVehicleEntryAction::isEligible(
                    $record
                )
            );
        }

        foreach ([
            VisitStatus::Draft,
            VisitStatus::Rejected,
            VisitStatus::InProgress,
            VisitStatus::Completed,
            VisitStatus::Cancelled,
            VisitStatus::Expired,
        ] as $status) {
            $record = $this->visitWithVehicle(
                status: $status,
                pendingRequest: new VisitVehicleAuthorizationRequestRecord
            );

            $this->assertFalse(
                RejectVehicleEntryAction::isEligible(
                    $record
                )
            );
        }

        $withoutVehicle = new VisitRecord;

        $withoutVehicle->forceFill([
            'status' => VisitStatus::Scheduled,
        ]);

        $withoutVehicle->setRelation(
            'vehicle',
            null
        );

        $this->assertFalse(
            RejectVehicleEntryAction::isEligible(
                $withoutVehicle
            )
        );

        $withoutPendingRequest = $this->visitWithVehicle(
            status: VisitStatus::Scheduled
        );

        $this->assertFalse(
            RejectVehicleEntryAction::isEligible(
                $withoutPendingRequest
            )
        );

        $alreadyAuthorized = $this->visitWithVehicle(
            status: VisitStatus::Scheduled,
            entryAuthorized: true,
            pendingRequest: new VisitVehicleAuthorizationRequestRecord
        );

        $this->assertFalse(
            RejectVehicleEntryAction::isEligible(
                $alreadyAuthorized
            )
        );
    }

    public function test_it_authorizes_and_calls_only_the_controlled_decision_use_case(): void
    {
        $source = $this->sourceOf(
            RejectVehicleEntryAction::class
        );

        foreach ([
            'Gate::authorize(',
            "'authorizeVehicleEntry'",
            'DecideVisitVehicleAuthorizationUseCase::class',
            'DecideVisitVehicleAuthorizationCommand(',
            'requestId:',
            'tenantId:',
            'organizationId:',
            'decidedByUserId:',
            'VisitVehicleAuthorizationStatus::Rejected',
            '->required()',
            '->minLength(5)',
            'isEligible(',
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }

        foreach ([
            '->fill([',
            '->update([',
            'VisitVehicleRecord::query()->',
            'RequestVisitVehicleAuthorizationUseCase',
            "'entry_authorized' =>",
            "'entry_authorized_by' =>",
            "'entry_authorized_at' =>",
            'Http::',
            'dispatch(',
            'openDoor',
            'unlock',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_the_visit_table_exposes_the_reject_vehicle_entry_action(): void
    {
        $table = file_get_contents(
            app_path(
                'Modules/Operations/UI/Filament/Resources/VisitRecords/Tables/VisitRecordsTable.php'
            )
        );

        $this->assertIsString($table);

        $this->assertStringContainsString(
            'RejectVehicleEntryAction::make()',
            $table
        );
    }

    private function visitWithVehicle(
        VisitStatus $status,
        bool $entryAuthorized = false,
        ?VisitVehicleAuthorizationRequestRecord $pendingRequest = null
    ): VisitRecord {
        $vehicle = new VisitVehicleRecord;

        $vehicle->forceFill([
            'entry_authorized' => $entryAuthorized,
        ]);

        $vehicle->setRelation(
            'pendingAuthorizationRequest',
            $pendingRequest
        );

        $visit = new VisitRecord;

        $visit->forceFill([
            'status' => $status,
        ]);

        $visit->setRelation(
            'vehicle',
            $vehicle
        );

        return $visit;
    }

    /**
     * @param  class-string  $class
     */
    private function sourceOf(string $class): string
    {
        $filename = (
            new ReflectionClass($class)
        )->getFileName();

        $this->assertIsString($filename);

        $source = file_get_contents($filename);

        $this->assertIsString($source);

        return $source;
    }
}
