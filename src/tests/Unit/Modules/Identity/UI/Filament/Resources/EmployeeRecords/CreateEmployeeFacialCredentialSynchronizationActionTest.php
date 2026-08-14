<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\UI\Filament\Resources\EmployeeRecords;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Actions\CreateEmployeeFacialCredentialSynchronizationAction;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoDerivativeRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CreateEmployeeFacialCredentialSynchronizationActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'facial_photos.intelbras_derivative.profile',
            'intelbras_facial_credential'
        );

        config()->set(
            'facial_photos.intelbras_derivative.policy_version',
            'intelbras-facial-credential-v1'
        );
    }

    public function test_it_requires_an_active_employee_with_an_approved_current_photo_and_ready_derivative(): void
    {
        self::assertFalse(
            CreateEmployeeFacialCredentialSynchronizationAction::isEligibleRecord(
                $this->employeeWithoutPhoto()
            )
        );

        self::assertFalse(
            CreateEmployeeFacialCredentialSynchronizationAction::isEligibleRecord(
                $this->employeeWithPhoto(
                    photoStatus: FacialPhotoStatus::PendingValidation,
                    derivativeStatus: FacialPhotoDerivativeStatus::Ready,
                )
            )
        );

        self::assertFalse(
            CreateEmployeeFacialCredentialSynchronizationAction::isEligibleRecord(
                $this->employeeWithPhoto(
                    photoStatus: FacialPhotoStatus::Approved,
                    derivativeStatus: FacialPhotoDerivativeStatus::Processing,
                )
            )
        );

        self::assertTrue(
            CreateEmployeeFacialCredentialSynchronizationAction::isEligibleRecord(
                $this->employeeWithPhoto(
                    photoStatus: FacialPhotoStatus::Approved,
                    derivativeStatus: FacialPhotoDerivativeStatus::Ready,
                )
            )
        );

        $inactive =
            $this->employeeWithPhoto(
                photoStatus: FacialPhotoStatus::Approved,
                derivativeStatus: FacialPhotoDerivativeStatus::Ready,
            );

        $inactive->status = 'inactive';

        self::assertFalse(
            CreateEmployeeFacialCredentialSynchronizationAction::isEligibleRecord(
                $inactive
            )
        );
    }

    public function test_the_action_is_manual_confirmed_and_creation_only(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Modules/Identity/UI/Filament/'
                .'Resources/EmployeeRecords/Actions/'
                .'CreateEmployeeFacialCredentialSynchronizationAction.php'
            )
        );

        self::assertIsString($source);

        self::assertStringContainsString(
            "'Preparar sincronização facial'",
            $source
        );

        self::assertStringContainsString(
            '->requiresConfirmation()',
            $source
        );

        self::assertStringContainsString(
            'Gate::authorize(',
            $source
        );

        self::assertStringContainsString(
            "'createFacialCredentialSynchronization'",
            $source
        );

        self::assertStringContainsString(
            'CreateFacialCredentialSynchronizationUseCase::class',
            $source
        );

        self::assertStringContainsString(
            'FacialCredentialSubjectType::Employee',
            $source
        );

        self::assertStringContainsString(
            'facialCredentialSynchronizations()',
            $source
        );

        self::assertStringContainsString(
            'Nenhuma imagem será enviada',
            $source
        );

        self::assertStringNotContainsString(
            'ExecuteFacialCredentialSynchronizationUseCase',
            $source
        );

        foreach (
            [
                'Http::',
                'Queue::',
                'Bus::',
                'Storage::',
                'dispatch(',
                'dispatchSync(',
                'curl_',
                'file_get_contents(',
                'base64_encode(',
                'base64_decode(',
            ] as $prohibited
        ) {
            self::assertStringNotContainsString(
                $prohibited,
                $source
            );
        }
    }

    public function test_the_action_does_not_reference_credentials_or_expose_sensitive_fingerprints(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Modules/Identity/UI/Filament/'
                .'Resources/EmployeeRecords/Actions/'
                .'CreateEmployeeFacialCredentialSynchronizationAction.php'
            )
        );

        self::assertIsString($source);

        foreach (
            [
                'credential_username',
                'credential_password',
                'plan_fingerprint',
                'context_fingerprint',
                'raw_payload',
            ] as $sensitive
        ) {
            self::assertStringNotContainsString(
                $sensitive,
                $source
            );
        }

        self::assertSame(
            1,
            substr_count(
                $source,
                'source_sha256'
            )
        );

        self::assertStringContainsString(
            '$candidate->source_sha256',
            $source
        );

    }

    public function test_employee_table_registers_creation_action(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Modules/Identity/UI/Filament/'
                .'Resources/EmployeeRecords/Tables/'
                .'EmployeeRecordsTable.php'
            )
        );

        self::assertIsString($source);

        self::assertStringContainsString(
            'CreateEmployeeFacialCredentialSynchronizationAction::make()',
            $source
        );
    }

    private function employeeWithoutPhoto(): EmployeeRecord
    {
        $employee = new EmployeeRecord([
            'status' => 'active',
        ]);

        $employee->setRelation(
            'latestFacialPhoto',
            null
        );

        return $employee;
    }

    private function employeeWithPhoto(
        FacialPhotoStatus $photoStatus,
        FacialPhotoDerivativeStatus $derivativeStatus,
    ): EmployeeRecord {
        $sourceSha256 = str_repeat(
            'a',
            64
        );

        $photo = new FacialPhotoRecord([
            'id' => (string) Str::uuid(),
            'status' => $photoStatus,
            'sha256' => $sourceSha256,
        ]);

        $derivative =
            new FacialPhotoDerivativeRecord([
                'id' => (string) Str::uuid(),
                'profile' => 'intelbras_facial_credential',
                'policy_version' => 'intelbras-facial-credential-v1',
                'status' => $derivativeStatus,
                'source_sha256' => $sourceSha256,
                'sha256' => str_repeat('b', 64),
                'width' => 800,
                'height' => 1000,
                'mime_type' => 'image/jpeg',
                'size_bytes' => 30_000,
            ]);

        $derivative->generated_at = now();

        $photo->setRelation(
            'derivatives',
            new Collection([
                $derivative,
            ])
        );

        $employee = new EmployeeRecord([
            'status' => 'active',
        ]);

        $employee->setRelation(
            'latestFacialPhoto',
            $photo
        );

        return $employee;
    }
}
