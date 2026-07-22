<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions;

use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\AuthorizeVisitAction;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\RejectVisitAction;
use Filament\Actions\Action;
use ReflectionClass;
use Tests\TestCase;

class VisitAuthorizationActionsTest extends TestCase
{
    public function test_it_creates_the_authorization_actions(): void
    {
        $authorize = AuthorizeVisitAction::make();
        $reject = RejectVisitAction::make();

        $this->assertInstanceOf(
            Action::class,
            $authorize
        );

        $this->assertSame(
            'authorizeVisit',
            $authorize->getName()
        );

        $this->assertInstanceOf(
            Action::class,
            $reject
        );

        $this->assertSame(
            'rejectVisit',
            $reject->getName()
        );
    }

    public function test_authorization_is_available_for_eligible_statuses(): void
    {
        foreach ([
            VisitStatus::Scheduled,
            VisitStatus::PendingAuthorization,
            VisitStatus::Rejected,
        ] as $status) {
            $record = $this->visitWithStatus(
                $status
            );

            $this->assertTrue(
                AuthorizeVisitAction::isEligible(
                    $record
                )
            );
        }

        foreach ([
            VisitStatus::Draft,
            VisitStatus::Authorized,
            VisitStatus::InProgress,
            VisitStatus::Completed,
            VisitStatus::Cancelled,
            VisitStatus::Expired,
        ] as $status) {
            $record = $this->visitWithStatus(
                $status
            );

            $this->assertFalse(
                AuthorizeVisitAction::isEligible(
                    $record
                )
            );
        }
    }

    public function test_rejection_is_available_only_before_a_final_decision(): void
    {
        foreach ([
            VisitStatus::Scheduled,
            VisitStatus::PendingAuthorization,
        ] as $status) {
            $record = $this->visitWithStatus(
                $status
            );

            $this->assertTrue(
                RejectVisitAction::isEligible(
                    $record
                )
            );
        }

        foreach ([
            VisitStatus::Draft,
            VisitStatus::Authorized,
            VisitStatus::Rejected,
            VisitStatus::InProgress,
            VisitStatus::Completed,
            VisitStatus::Cancelled,
            VisitStatus::Expired,
        ] as $status) {
            $record = $this->visitWithStatus(
                $status
            );

            $this->assertFalse(
                RejectVisitAction::isEligible(
                    $record
                )
            );
        }
    }

    public function test_actions_authorize_and_call_only_the_controlled_use_cases(): void
    {
        $authorizeSource = $this->sourceOf(
            AuthorizeVisitAction::class
        );

        foreach ([
            'Gate::authorize(',
            "'operateGatehouse'",
            'AuthorizeVisitUseCase::class',
            'AuthorizeVisitCommand(',
            'authorizerEmployeeId:',
            'recordedByUserId:',
            'VisitAuthorizationMethod::options()',
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $authorizeSource
            );
        }

        $rejectSource = $this->sourceOf(
            RejectVisitAction::class
        );

        foreach ([
            'Gate::authorize(',
            "'operateGatehouse'",
            'RejectVisitUseCase::class',
            'RejectVisitCommand(',
            'operatorUserId:',
            'rejection_reason',
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $rejectSource
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
                $authorizeSource
            );

            $this->assertStringNotContainsString(
                $forbidden,
                $rejectSource
            );
        }
    }

    public function test_the_visit_table_exposes_both_actions(): void
    {
        $table = file_get_contents(
            app_path(
                'Modules/Operations/UI/Filament/Resources/VisitRecords/Tables/VisitRecordsTable.php'
            )
        );

        $this->assertIsString($table);

        $this->assertStringContainsString(
            'AuthorizeVisitAction::make()',
            $table
        );

        $this->assertStringContainsString(
            'RejectVisitAction::make()',
            $table
        );
    }

    private function visitWithStatus(
        VisitStatus $status
    ): VisitRecord {
        $record = new VisitRecord;

        $record->forceFill([
            'status' => $status,
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
