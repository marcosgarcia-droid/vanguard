<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\UI\Filament\Resources\EmployeeRecords;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Schemas\EmployeeFacialPhotoDerivativePresentation;
use App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Schemas\EmployeeFacialPhotoStatusPresentation;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Tests\TestCase;

final class EmployeeFacialPhotoPresentationTest extends TestCase
{
    public function test_employee_exposes_the_latest_facial_photo_relation(): void
    {
        $employee = new EmployeeRecord;

        $this->assertInstanceOf(
            MorphOne::class,
            $employee->latestFacialPhoto()
        );
    }

    public function test_status_presentation_uses_safe_portuguese_labels(): void
    {
        $this->assertSame(
            [
                'label' => 'Não cadastrada',
                'color' => 'gray',
            ],
            EmployeeFacialPhotoStatusPresentation::forStatus(
                null
            )
        );

        $this->assertSame(
            'success',
            EmployeeFacialPhotoStatusPresentation::forStatus(
                FacialPhotoStatus::Approved
            )['color']
        );

        $this->assertSame(
            'danger',
            EmployeeFacialPhotoStatusPresentation::forStatus(
                FacialPhotoStatus::Rejected
            )['color']
        );
    }

    public function test_presenters_accept_an_employee_without_a_facial_photo(): void
    {
        $employee = new EmployeeRecord;

        $employee->setRelation(
            'latestFacialPhoto',
            null
        );

        $this->assertSame(
            'Não cadastrada',
            EmployeeFacialPhotoStatusPresentation::summary(
                $employee
            )['label']
        );

        $this->assertSame(
            'Não iniciada',
            EmployeeFacialPhotoDerivativePresentation::summary(
                $employee
            )['label']
        );

        $this->assertSame(
            'Não realizada',
            EmployeeFacialPhotoStatusPresentation::validationSummary(
                $employee
            )
        );
    }

    public function test_validation_summary_uses_the_current_biometric_photo(): void
    {
        $employee = new EmployeeRecord;

        $photo = new FacialPhotoRecord([
            'status' => FacialPhotoStatus::Approved,
            'approved_at' => '2026-08-12 10:30:00',
        ]);

        $employee->setRelation(
            'latestFacialPhoto',
            $photo
        );

        $this->assertStringContainsString(
            'Aprovada em',
            EmployeeFacialPhotoStatusPresentation::validationSummary(
                $employee
            )
        );
    }

    public function test_employee_infolist_has_a_dedicated_biometric_section(): void
    {
        $source = file_get_contents(
            app_path(
                'Modules/Identity/UI/Filament/Resources/'
                .'EmployeeRecords/Schemas/EmployeeRecordInfolist.php'
            )
        );

        $this->assertIsString(
            $source
        );

        foreach (
            [
                "Tab::make('Biometria facial')",
                "Section::make('Identidade biométrica')",
                "'Situação da foto facial'",
                "'Última validação'",
                "'Última captura'",
                "'Preparação da foto facial'",
                "'Detalhes da preparação'",
                "'Orientações para a foto facial'",
                'EmployeeFacialPhotoStatusPresentation::summary(',
                'EmployeeFacialPhotoDerivativePresentation::summary(',
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }

        foreach (
            [
                'sha256',
                'validation_result',
                'rejection_reasons',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_employee_table_eager_loads_and_presents_facial_state(): void
    {
        $source = file_get_contents(
            app_path(
                'Modules/Identity/UI/Filament/Resources/'
                .'EmployeeRecords/Tables/EmployeeRecordsTable.php'
            )
        );

        $this->assertIsString(
            $source
        );

        foreach (
            [
                "'latestFacialPhoto.latestValidationAttempt'",
                "'latestFacialPhoto.derivatives.latestAttempt'",
                "TextColumn::make('facial_photo_status')",
                "->label('Foto facial')",
                "TextColumn::make('facial_photo_derivative_status')",
                "->label('Preparação facial')",
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }
    }
}
