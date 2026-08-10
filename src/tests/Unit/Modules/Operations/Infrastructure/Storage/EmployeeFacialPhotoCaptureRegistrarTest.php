<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Storage;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoException;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoFailure;
use App\Modules\Operations\Application\FacialPhotos\Validation\Schedule\FacialPhotoValidationAfterCommitScheduler;
use App\Modules\Operations\Application\FacialPhotos\Validation\Schedule\ScheduleFacialPhotoValidationCommand;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoConfirmationConsumptionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Storage\EmployeeFacialPhotoCaptureRegistrar;
use GdImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

final class EmployeeFacialPhotoCaptureRegistrarTest extends TestCase
{
    use RefreshDatabase;

    private const CONFIRMATION_KEY =
        'abababababababababababababababab'
        .'abababababababababababababababab';

    private string $directory;

    private EmployeeFacialPhotoValidationSchedulerSpy $scheduler;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('facial_photos');

        $this->directory = storage_path(
            'framework/testing/employee-facial-photo-capture'
        );

        File::deleteDirectory(
            $this->directory
        );

        File::ensureDirectoryExists(
            $this->directory
        );

        $this->scheduler =
            new EmployeeFacialPhotoValidationSchedulerSpy;

        app()->instance(
            FacialPhotoValidationAfterCommitScheduler::class,
            $this->scheduler
        );
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(
            $this->directory
        );

        parent::tearDown();
    }

    public function test_active_employee_receives_biometric_photo_without_changing_profile_photo(): void
    {
        [$employee, $user] = $this->employee(
            status: 'active',
            code: 'EMP-CAP-001'
        );

        $profile = [
            'disk' => $employee->photo_disk,
            'path' => $employee->photo_path,
            'uploaded_at' => $employee
                ->photo_uploaded_at
                ?->toDateTimeString(),
        ];

        $upload = $this->checkerboardUpload(
            'employee-webcam.jpg'
        );

        $result = app(
            EmployeeFacialPhotoCaptureRegistrar::class
        )->register(
            employee: $employee,
            upload: $upload,
            expectedSha256: $this->fingerprintForUpload(
                $upload
            ),
            source: FacialPhotoSource::Webcam,
            confirmationKey: self::CONFIRMATION_KEY,
            confirmationContext: EmployeeFacialPhotoCaptureRegistrar::confirmationContext(
                $employee
            ),
            createdBy: $user->id,
        );

        $photo = FacialPhotoRecord::query()
            ->findOrFail($result->photoId);

        $this->assertSame(
            EmployeeRecord::class,
            $photo->subject_type
        );

        $this->assertSame(
            $employee->id,
            $photo->subject_id
        );

        $this->assertSame(
            FacialPhotoSource::Webcam,
            $photo->source
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $photo->status
        );

        $this->assertTrue(
            $photo->subject->is($employee)
        );

        $consumption =
            FacialPhotoConfirmationConsumptionRecord::query()
                ->sole();

        $this->assertSame(
            EmployeeRecord::class,
            $consumption->subject_type
        );

        $this->assertSame(
            $employee->id,
            $consumption->subject_id
        );

        $this->assertNull(
            $consumption->visitor_id
        );

        $this->assertSame(
            EmployeeFacialPhotoCaptureRegistrar::confirmationContext(
                $employee
            ),
            $consumption->confirmation_context
        );

        $this->assertSame(
            1,
            $this->scheduler->calls
        );

        $this->assertNotNull(
            $this->scheduler->command
        );

        $this->assertSame(
            $result->photoId,
            $this->scheduler->command?->photoId
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $this->scheduler->command?->status
        );

        $this->assertSame(
            $user->id,
            $this->scheduler->command?->operatorUserId
        );

        $media = $photo->getFirstMedia(
            FacialPhotoRecord::ORIGINAL_COLLECTION
        );

        $this->assertInstanceOf(
            Media::class,
            $media
        );

        Storage::disk('facial_photos')
            ->assertExists(
                $media->getPathRelativeToRoot()
            );

        $employee->refresh();

        $this->assertSame(
            $profile['disk'],
            $employee->photo_disk
        );

        $this->assertSame(
            $profile['path'],
            $employee->photo_path
        );

        $this->assertSame(
            $profile['uploaded_at'],
            $employee->photo_uploaded_at
                ?->toDateTimeString()
        );
    }

    public function test_it_rejects_a_confirmation_context_from_another_subject_flow(): void
    {
        [$employee] = $this->employee(
            status: 'active',
            code: 'EMP-CAP-CONTEXT'
        );

        $upload = $this->checkerboardUpload(
            'employee-context.jpg'
        );

        try {
            app(
                EmployeeFacialPhotoCaptureRegistrar::class
            )->register(
                employee: $employee,
                upload: $upload,
                expectedSha256: $this->fingerprintForUpload(
                    $upload
                ),
                source: FacialPhotoSource::FileUpload,
                confirmationKey: self::CONFIRMATION_KEY,
                confirmationContext: 'visitor.update.'
                    .$employee->id
                    .'.photo_capture',
            );

            $this->fail(
                'Contexto de Visitor não pode confirmar biometria de Employee.'
            );
        } catch (
            RegisterFacialPhotoException $exception
        ) {
            $this->assertSame(
                RegisterFacialPhotoFailure::InvalidConfirmationProof,
                $exception->failure
            );
        }

        $this->assertDatabaseCount(
            'facial_photos',
            0
        );

        $this->assertDatabaseCount(
            'facial_photo_confirmation_consumptions',
            0
        );

        $this->assertDatabaseCount(
            'media',
            0
        );

        $this->assertSame(
            [],
            Storage::disk('facial_photos')
                ->allFiles()
        );

        $this->assertSame(
            0,
            $this->scheduler->calls
        );
    }

    public function test_inactive_employee_is_rejected_without_scheduling_or_profile_change(): void
    {
        [$employee] = $this->employee(
            status: 'inactive',
            code: 'EMP-CAP-INACTIVE'
        );

        $profilePath = $employee->photo_path;

        $upload = $this->checkerboardUpload(
            'employee-inactive.jpg'
        );

        try {
            app(
                EmployeeFacialPhotoCaptureRegistrar::class
            )->register(
                employee: $employee,
                upload: $upload,
                expectedSha256: $this->fingerprintForUpload(
                    $upload
                ),
                source: FacialPhotoSource::FileUpload,
                confirmationKey: str_repeat(
                    'c',
                    64
                ),
                confirmationContext: EmployeeFacialPhotoCaptureRegistrar::confirmationContext(
                    $employee
                ),
            );

            $this->fail(
                'Employee inativo não deveria aceitar captura facial.'
            );
        } catch (
            RegisterFacialPhotoException $exception
        ) {
            $this->assertSame(
                RegisterFacialPhotoFailure::SubjectUnavailable,
                $exception->failure
            );
        }

        $this->assertDatabaseCount(
            'facial_photos',
            0
        );

        $this->assertDatabaseCount(
            'facial_photo_confirmation_consumptions',
            0
        );

        $this->assertDatabaseCount(
            'media',
            0
        );

        $this->assertSame(
            0,
            $this->scheduler->calls
        );

        $employee->refresh();

        $this->assertSame(
            $profilePath,
            $employee->photo_path
        );
    }

    public function test_it_compensates_biometric_media_when_surrounding_transaction_rolls_back(): void
    {
        [$employee] = $this->employee(
            status: 'active',
            code: 'EMP-CAP-ROLLBACK'
        );

        $upload = $this->checkerboardUpload(
            'employee-rollback.jpg'
        );

        $connection = DB::connection();

        $startingTransactionLevel =
            $connection->transactionLevel();

        $facialPath = null;
        $rolledBack = false;

        $connection->beginTransaction();

        try {
            $result = app(
                EmployeeFacialPhotoCaptureRegistrar::class
            )->register(
                employee: $employee,
                upload: $upload,
                expectedSha256: $this->fingerprintForUpload(
                    $upload
                ),
                source: FacialPhotoSource::FileUpload,
                confirmationKey: str_repeat(
                    'd',
                    64
                ),
                confirmationContext: EmployeeFacialPhotoCaptureRegistrar::confirmationContext(
                    $employee
                ),
            );

            $photo = FacialPhotoRecord::query()
                ->findOrFail(
                    $result->photoId
                );

            $media = $photo->getFirstMedia(
                FacialPhotoRecord::ORIGINAL_COLLECTION
            );

            $this->assertInstanceOf(
                Media::class,
                $media
            );

            $facialPath =
                $media->getPathRelativeToRoot();

            Storage::disk('facial_photos')
                ->assertExists(
                    $facialPath
                );

            $this->assertDatabaseCount(
                'facial_photos',
                1
            );

            $this->assertDatabaseCount(
                'media',
                1
            );

            $this->assertDatabaseCount(
                'facial_photo_confirmation_consumptions',
                1
            );

            $connection->rollBack(
                $startingTransactionLevel
            );

            $rolledBack = true;
        } finally {
            if (
                ! $rolledBack
                && $connection->transactionLevel()
                    > $startingTransactionLevel
            ) {
                $connection->rollBack(
                    $startingTransactionLevel
                );
            }
        }

        $this->assertDatabaseCount(
            'facial_photos',
            0
        );

        $this->assertDatabaseCount(
            'media',
            0
        );

        $this->assertDatabaseCount(
            'facial_photo_confirmation_consumptions',
            0
        );

        $this->assertIsString(
            $facialPath
        );

        Storage::disk('facial_photos')
            ->assertMissing(
                $facialPath
            );

        $employee->refresh();

        $this->assertSame(
            'employees/profile/existing.jpg',
            $employee->photo_path
        );
    }

    /**
     * @return array{EmployeeRecord, User}
     */
    private function employee(
        string $status,
        string $code
    ): array {
        $tenant = TenantRecord::query()
            ->create([
                'id' => (string) Str::uuid(),
                'name' => 'GRUPO CAPTURA EMPLOYEE '.$code,
                'status' => 'active',
            ]);

        $organization =
            OrganizationRecord::query()
                ->create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'status' => 'active',
                    'legal_name' => 'UNIDADE CAPTURA EMPLOYEE '.$code,
                    'display_name' => 'CAPTURA EMPLOYEE '.$code,
                    'unit_code' => $code,
                ]);

        $employee = EmployeeRecord::query()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'employee_code' => $code,
                'full_name' => 'FUNCIONÁRIO CAPTURA '.$code,
                'status' => $status,
                'photo_disk' => 'local',
                'photo_path' => 'employees/profile/existing.jpg',
                'photo_uploaded_at' => now()
                    ->subDay()
                    ->startOfSecond(),
                'hired_at' => '2026-01-01',
            ]);

        $user = User::factory()->create();

        return [
            $employee,
            $user,
        ];
    }

    private function fingerprintForUpload(
        UploadedFile $upload
    ): string {
        $absolutePath = $upload->getRealPath();

        $this->assertIsString(
            $absolutePath
        );

        $fingerprint = hash_file(
            'sha256',
            $absolutePath
        );

        $this->assertIsString(
            $fingerprint
        );

        return $fingerprint;
    }

    private function checkerboardUpload(
        string $originalFileName
    ): UploadedFile {
        $width = 720;
        $height = 900;

        $image = imagecreatetruecolor(
            $width,
            $height
        );

        $this->assertInstanceOf(
            GdImage::class,
            $image
        );

        $dark = imagecolorallocate(
            $image,
            35,
            35,
            35
        );

        $light = imagecolorallocate(
            $image,
            220,
            220,
            220
        );

        $block = 24;

        for (
            $y = 0;
            $y < $height;
            $y += $block
        ) {
            for (
                $x = 0;
                $x < $width;
                $x += $block
            ) {
                $alternate = (
                    intdiv(
                        $x,
                        $block
                    )
                    + intdiv(
                        $y,
                        $block
                    )
                ) % 2 === 0;

                imagefilledrectangle(
                    $image,
                    $x,
                    $y,
                    min(
                        $x + $block - 1,
                        $width - 1
                    ),
                    min(
                        $y + $block - 1,
                        $height - 1
                    ),
                    $alternate
                        ? $dark
                        : $light
                );
            }
        }

        $path = $this->directory
            .DIRECTORY_SEPARATOR
            .Str::uuid()
            .'.jpg';

        $this->assertTrue(
            imagejpeg(
                $image,
                $path,
                92
            )
        );

        imagedestroy(
            $image
        );

        return new UploadedFile(
            path: $path,
            originalName: $originalFileName,
            mimeType: 'image/jpeg',
            error: null,
            test: true,
        );
    }
}

final class EmployeeFacialPhotoValidationSchedulerSpy implements FacialPhotoValidationAfterCommitScheduler
{
    public int $calls = 0;

    public ?ScheduleFacialPhotoValidationCommand $command =
        null;

    public function schedule(
        ScheduleFacialPhotoValidationCommand $command
    ): bool {
        $this->calls++;
        $this->command = $command;

        return true;
    }
}
