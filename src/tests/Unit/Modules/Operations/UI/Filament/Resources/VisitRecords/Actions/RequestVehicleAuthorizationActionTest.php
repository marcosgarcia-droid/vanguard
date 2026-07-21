<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions;

use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitVehicleAuthorizationRequestRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitVehicleRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\RequestVehicleAuthorizationAction;
use Filament\Actions\Action;
use ReflectionClass;
use Tests\TestCase;

class RequestVehicleAuthorizationActionTest extends TestCase
{
    public function test_it_creates_the_vehicle_authorization_request_action(): void
    {
        $action = RequestVehicleAuthorizationAction::make();

        $this->assertInstanceOf(
            Action::class,
            $action
        );

        $this->assertSame(
            'requestVehicleAuthorization',
            $action->getName()
        );
    }

    public function test_it_accepts_only_eligible_visits_with_an_unauthorized_vehicle(): void
    {
        foreach ([
            VisitStatus::Scheduled,
            VisitStatus::PendingAuthorization,
            VisitStatus::Authorized,
        ] as $status) {
            $record = $this->visitWithVehicle(
                status: $status
            );

            $this->assertTrue(
                RequestVehicleAuthorizationAction::isEligible(
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
                status: $status
            );

            $this->assertFalse(
                RequestVehicleAuthorizationAction::isEligible(
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
            RequestVehicleAuthorizationAction::isEligible(
                $withoutVehicle
            )
        );

        $authorizedVehicle = $this->visitWithVehicle(
            status: VisitStatus::Scheduled,
            entryAuthorized: true
        );

        $this->assertFalse(
            RequestVehicleAuthorizationAction::isEligible(
                $authorizedVehicle
            )
        );

        $pendingRequest = new VisitVehicleAuthorizationRequestRecord;

        $vehicleWithPendingRequest = $this->visitWithVehicle(
            status: VisitStatus::Scheduled,
            pendingRequest: $pendingRequest
        );

        $this->assertFalse(
            RequestVehicleAuthorizationAction::isEligible(
                $vehicleWithPendingRequest
            )
        );

        $rejectedRequest = new VisitVehicleAuthorizationRequestRecord;

        $vehicleWithRejectedRequest = $this->visitWithVehicle(
            status: VisitStatus::Scheduled,
            latestRequest: $rejectedRequest
        );

        $this->assertFalse(
            RequestVehicleAuthorizationAction::isEligible(
                $vehicleWithRejectedRequest
            )
        );
    }

    public function test_it_authorizes_and_calls_only_the_controlled_request_use_case(): void
    {
        $source = $this->sourceOf(
            RequestVehicleAuthorizationAction::class
        );

        foreach ([
            'Gate::authorize(',
            "'update'",
            'RequestVisitVehicleAuthorizationUseCase::class',
            'RequestVisitVehicleAuthorizationCommand(',
            'visitVehicleId:',
            'tenantId:',
            'organizationId:',
            'requestedByUserId:',
            'idempotencyKey:',
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
            'DecideVisitVehicleAuthorizationUseCase',
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

    public function test_the_visit_table_exposes_the_vehicle_authorization_request_action(): void
    {
        $table = file_get_contents(
            app_path(
                'Modules/Operations/UI/Filament/Resources/VisitRecords/Tables/VisitRecordsTable.php'
            )
        );

        $this->assertIsString($table);

        $this->assertStringContainsString(
            'RequestVehicleAuthorizationAction::make()',
            $table
        );

        $this->assertStringContainsString(
            "'vehicle.latestAuthorizationRequest'",
            $table
        );

        $this->assertStringContainsString(
            "'vehicle.pendingAuthorizationRequest'",
            $table
        );
    }

    private function visitWithVehicle(
        VisitStatus $status,
        bool $entryAuthorized = false,
        ?VisitVehicleAuthorizationRequestRecord $pendingRequest = null,
        ?VisitVehicleAuthorizationRequestRecord $latestRequest = null
    ): VisitRecord {
        $vehicle = new VisitVehicleRecord;

        $vehicle->forceFill([
            'entry_authorized' => $entryAuthorized,
        ]);

        $vehicle->setRelation(
            'pendingAuthorizationRequest',
            $pendingRequest
        );

        $vehicle->setRelation(
            'latestAuthorizationRequest',
            $latestRequest ?? $pendingRequest
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
