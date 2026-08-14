<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\UI\Filament\Resources\EmployeeRecords;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Actions\ReprocessEmployeeFacialPhotoDerivativeAction;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use Tests\TestCase;

final class ReprocessEmployeeFacialPhotoDerivativeActionTest extends TestCase
{
    public function test_employee_table_registers_the_reprocessing_action(): void
    {
        $table = file_get_contents(
            app_path(
                'Modules/Identity/UI/Filament/Resources/'
                .'EmployeeRecords/Tables/EmployeeRecordsTable.php'
            )
        );

        $this->assertIsString(
            $table
        );

        $this->assertStringContainsString(
            'ReprocessEmployeeFacialPhotoDerivativeAction::make()',
            $table
        );
    }

    public function test_action_uses_employee_subject_and_dedicated_permission(): void
    {
        $action = $this->actionSource();

        foreach (
            [
                'Action::make(',
                "'reprocessFacialPhotoDerivative'",
                'Gate::authorize(',
                'FacialPhotoSubjectType::Employee',
                'ReprocessFacialPhotoDerivativeUseCase::class',
                'ReprocessFacialPhotoDerivativeCommand(',
                'EmployeeFacialPhotoDerivativeReprocessingAudit::success(',
                'EmployeeFacialPhotoDerivativeReprocessingAudit::failure(',
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $action
            );
        }

        foreach (
            [
                'FacialPhotoSubjectType::Visitor',
                'VisitorRecord',
                'VisitorFacialPhotoDerivativeReprocessingAudit',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $action
            );
        }
    }

    public function test_audit_calls_use_employee_named_argument(): void
    {
        $action = $this->actionSource();

        $this->assertSame(
            2,
            substr_count(
                $action,
                'employee: $record'
            )
        );

        $this->assertStringNotContainsString(
            'visitor: $record',
            $action
        );
    }

    public function test_approved_employee_photo_without_derivative_is_eligible(): void
    {
        config()->set(
            'facial_photos.normalization.enabled',
            true
        );

        config()->set(
            'facial_photos.normalization.async_generation.enabled',
            true
        );

        $employee = new EmployeeRecord;

        $photo = new FacialPhotoRecord([
            'status' => FacialPhotoStatus::Approved,
            'sha256' => str_repeat(
                'a',
                64
            ),
        ]);

        $employee->setRelation(
            'latestFacialPhoto',
            $photo
        );

        $this->assertTrue(
            ReprocessEmployeeFacialPhotoDerivativeAction::isEligibleRecord(
                $employee
            )
        );
    }

    public function test_employee_without_approved_photo_is_not_eligible(): void
    {
        config()->set(
            'facial_photos.normalization.enabled',
            true
        );

        config()->set(
            'facial_photos.normalization.async_generation.enabled',
            true
        );

        $employee = new EmployeeRecord;

        $employee->setRelation(
            'latestFacialPhoto',
            null
        );

        $this->assertFalse(
            ReprocessEmployeeFacialPhotoDerivativeAction::isEligibleRecord(
                $employee
            )
        );
    }

    public function test_audit_events_are_employee_specific_and_translated(): void
    {
        $audit = file_get_contents(
            app_path(
                'Modules/Identity/UI/Filament/Resources/'
                .'EmployeeRecords/Actions/'
                .'EmployeeFacialPhotoDerivativeReprocessingAudit.php'
            )
        );

        $presenter = file_get_contents(
            app_path(
                'Support/ActivityLog/VanguardActivityLogPresenter.php'
            )
        );

        $this->assertIsString(
            $audit
        );

        $this->assertIsString(
            $presenter
        );

        foreach (
            [
                'employee_facial_photo_derivative_reprocessing_requested',
                'employee_facial_photo_derivative_reprocessing_failed',
            ] as $event
        ) {
            $this->assertStringContainsString(
                $event,
                $audit
            );

            $this->assertStringContainsString(
                $event,
                $presenter
            );
        }

        $this->assertStringNotContainsString(
            'visitor_facial_photo_derivative',
            $audit
        );
    }

    private function actionSource(): string
    {
        $action = file_get_contents(
            app_path(
                'Modules/Identity/UI/Filament/Resources/'
                .'EmployeeRecords/Actions/'
                .'ReprocessEmployeeFacialPhotoDerivativeAction.php'
            )
        );

        $this->assertIsString(
            $action
        );

        return $action;
    }
}
