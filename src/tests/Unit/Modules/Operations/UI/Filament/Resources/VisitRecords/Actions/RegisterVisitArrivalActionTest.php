<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions;

use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\RegisterVisitArrivalAction;
use Filament\Actions\Action;
use ReflectionClass;
use Tests\TestCase;

class RegisterVisitArrivalActionTest extends TestCase
{
    public function test_it_creates_the_register_arrival_action(): void
    {
        $action = RegisterVisitArrivalAction::make();

        $this->assertInstanceOf(
            Action::class,
            $action
        );

        $this->assertSame(
            'registerVisitArrival',
            $action->getName()
        );
    }

    public function test_it_accepts_only_visits_without_a_registered_arrival(): void
    {
        foreach ([
            VisitStatus::Scheduled,
            VisitStatus::PendingAuthorization,
            VisitStatus::Authorized,
        ] as $status) {
            $record = new VisitRecord;

            $record->forceFill([
                'status' => $status,
                'arrived_at' => null,
            ]);

            $this->assertTrue(
                RegisterVisitArrivalAction::isEligible(
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
            $record = new VisitRecord;

            $record->forceFill([
                'status' => $status,
                'arrived_at' => null,
            ]);

            $this->assertFalse(
                RegisterVisitArrivalAction::isEligible(
                    $record
                )
            );
        }

        $alreadyArrived = new VisitRecord;

        $alreadyArrived->forceFill([
            'status' => VisitStatus::PendingAuthorization,
            'arrived_at' => now(),
        ]);

        $this->assertFalse(
            RegisterVisitArrivalAction::isEligible(
                $alreadyArrived
            )
        );
    }

    public function test_it_authorizes_and_calls_only_the_controlled_use_case(): void
    {
        $source = $this->sourceOf(
            RegisterVisitArrivalAction::class
        );

        foreach ([
            'Gate::authorize(',
            "'operateGatehouse'",
            'RegisterVisitArrivalUseCase::class',
            'RegisterVisitArrivalCommand(',
            'visitId:',
            'operatorUserId:',
            'requiresConfirmation()',
            'isEligible(',
            'VisitHostNotifier::class',
            "wasChanged('arrived_at')",
            'notifyArrival($visit)',
            'report($exception)',
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }

        foreach ([
            '->fill([',
            '->update([',
            'VisitRecord::query()->',
            'CheckInVisitUseCase',
            'CheckOutVisitUseCase',
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

    public function test_the_visit_table_exposes_the_register_arrival_action(): void
    {
        $table = file_get_contents(
            app_path(
                'Modules/Operations/UI/Filament/Resources/VisitRecords/Tables/VisitRecordsTable.php'
            )
        );

        $this->assertIsString($table);

        $this->assertStringContainsString(
            'RegisterVisitArrivalAction::make()',
            $table
        );
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
