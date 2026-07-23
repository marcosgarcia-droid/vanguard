<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions;

use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\CancelVisitAction;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\CheckInVisitAction;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\CheckOutVisitAction;
use Filament\Actions\Action;
use ReflectionClass;
use Tests\TestCase;

class VisitFlowActionsTest extends TestCase
{
    public function test_it_creates_the_operational_flow_actions(): void
    {
        $checkIn = CheckInVisitAction::make();
        $checkOut = CheckOutVisitAction::make();
        $cancel = CancelVisitAction::make();

        $this->assertInstanceOf(
            Action::class,
            $checkIn
        );

        $this->assertSame(
            'checkInVisit',
            $checkIn->getName()
        );

        $this->assertInstanceOf(
            Action::class,
            $checkOut
        );

        $this->assertSame(
            'checkOutVisit',
            $checkOut->getName()
        );

        $this->assertInstanceOf(
            Action::class,
            $cancel
        );

        $this->assertSame(
            'cancelVisit',
            $cancel->getName()
        );
    }

    public function test_manual_entry_is_available_only_for_an_authorized_visit(): void
    {
        $eligible = $this->visit(
            status: VisitStatus::Authorized,
            checkedInAt: null,
        );

        $alreadyCheckedIn = $this->visit(
            status: VisitStatus::Authorized,
            checkedInAt: now(),
        );

        $this->assertTrue(
            CheckInVisitAction::isEligible(
                $eligible
            )
        );

        $this->assertFalse(
            CheckInVisitAction::isEligible(
                $alreadyCheckedIn
            )
        );

        foreach ([
            VisitStatus::Draft,
            VisitStatus::Scheduled,
            VisitStatus::PendingAuthorization,
            VisitStatus::Rejected,
            VisitStatus::InProgress,
            VisitStatus::Completed,
            VisitStatus::Cancelled,
            VisitStatus::Expired,
        ] as $status) {
            $this->assertFalse(
                CheckInVisitAction::isEligible(
                    $this->visit($status)
                )
            );
        }
    }

    public function test_manual_exit_is_available_only_for_a_visit_in_progress(): void
    {
        $eligible = $this->visit(
            status: VisitStatus::InProgress,
            checkedOutAt: null,
        );

        $alreadyCheckedOut = $this->visit(
            status: VisitStatus::InProgress,
            checkedOutAt: now(),
        );

        $this->assertTrue(
            CheckOutVisitAction::isEligible(
                $eligible
            )
        );

        $this->assertFalse(
            CheckOutVisitAction::isEligible(
                $alreadyCheckedOut
            )
        );

        foreach ([
            VisitStatus::Draft,
            VisitStatus::Scheduled,
            VisitStatus::PendingAuthorization,
            VisitStatus::Authorized,
            VisitStatus::Rejected,
            VisitStatus::Completed,
            VisitStatus::Cancelled,
            VisitStatus::Expired,
        ] as $status) {
            $this->assertFalse(
                CheckOutVisitAction::isEligible(
                    $this->visit($status)
                )
            );
        }
    }

    public function test_cancellation_is_available_only_before_entry(): void
    {
        foreach ([
            VisitStatus::Draft,
            VisitStatus::Scheduled,
            VisitStatus::PendingAuthorization,
            VisitStatus::Authorized,
        ] as $status) {
            $this->assertTrue(
                CancelVisitAction::isEligible(
                    $this->visit($status)
                )
            );
        }

        foreach ([
            VisitStatus::Rejected,
            VisitStatus::InProgress,
            VisitStatus::Completed,
            VisitStatus::Cancelled,
            VisitStatus::Expired,
        ] as $status) {
            $this->assertFalse(
                CancelVisitAction::isEligible(
                    $this->visit($status)
                )
            );
        }
    }

    public function test_actions_authorize_and_call_only_their_controlled_use_cases(): void
    {
        $expectations = [
            CheckInVisitAction::class => [
                'ability' => 'operateGatehouse',
                'values' => [
                    'CheckInVisitUseCase::class',
                    'CheckInVisitCommand(',
                    'operatorUserId:',
                    'requiresConfirmation()',
                ],
            ],
            CheckOutVisitAction::class => [
                'ability' => 'operateGatehouse',
                'values' => [
                    'CheckOutVisitUseCase::class',
                    'CheckOutVisitCommand(',
                    'operatorUserId:',
                    'requiresConfirmation()',
                ],
            ],
            CancelVisitAction::class => [
                'ability' => 'update',
                'values' => [
                    'CancelVisitUseCase::class',
                    'CancelVisitCommand(',
                    'operatorUserId:',
                    'cancellation_reason',
                    'VisitHostNotifier::class',
                    'closeDecisionActions(',
                    'notifyCancelled(',
                    'wasChanged(',
                    "'cancelled_at'",
                ],
            ],
        ];

        foreach ($expectations as $class => $expectation) {
            $source = $this->sourceOf(
                $class
            );

            $this->assertStringContainsString(
                'Gate::authorize(',
                $source
            );

            $this->assertStringContainsString(
                "'{$expectation['ability']}'",
                $source
            );

            foreach ($expectation['values'] as $expected) {
                $this->assertStringContainsString(
                    $expected,
                    $source
                );
            }

            foreach ([
                '->fill([',
                '->update([',
                'VisitRecord::query()->',
                'Http::',
                'dispatch(',
                'openDoor',
                'unlock',
                'setConfig',
            ] as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $source
                );
            }
        }
    }

    public function test_the_visit_table_exposes_the_operational_flow_actions(): void
    {
        $table = file_get_contents(
            app_path(
                'Modules/Operations/UI/Filament/Resources/VisitRecords/Tables/VisitRecordsTable.php'
            )
        );

        $this->assertIsString($table);

        foreach ([
            'CheckInVisitAction::make()',
            'CheckOutVisitAction::make()',
            'CancelVisitAction::make()',
        ] as $action) {
            $this->assertStringContainsString(
                $action,
                $table
            );
        }
    }

    private function visit(
        VisitStatus $status,
        mixed $checkedInAt = null,
        mixed $checkedOutAt = null,
    ): VisitRecord {
        $record = new VisitRecord;

        $record->forceFill([
            'status' => $status,
            'checked_in_at' => $checkedInAt,
            'checked_out_at' => $checkedOutAt,
        ]);

        return $record;
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
