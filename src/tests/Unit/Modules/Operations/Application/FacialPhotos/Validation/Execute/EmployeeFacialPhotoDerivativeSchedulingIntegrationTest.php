<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Validation\Execute;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationCommand;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\FacialPhotoValidationExecutor;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Queue\GenerateFacialPhotoDerivativeJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class EmployeeFacialPhotoDerivativeSchedulingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(
            'facial_photos'
        );

        $this->directory = storage_path(
            'framework/testing/employee-facial-derivative'
        );

        File::deleteDirectory(
            $this->directory
        );

        File::ensureDirectoryExists(
            $this->directory
        );

        $this->configureFlow();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(
            $this->directory
        );

        parent::tearDown();
    }

    public function test_employee_approval_schedules_derivative_by_photo_id_without_subject_specific_context(): void
    {
        Bus::fake();

        [
            'employee' => $employee,
            'operator' => $operator,
            'photo' => $photo,
        ] = $this->createContext();

        $result = app(
            FacialPhotoValidationExecutor::class
        )->execute(
            new ExecuteFacialPhotoValidationCommand(
                photoId: $photo->id,
                operatorUserId: $operator->id,
            )
        );

        $this->assertTrue(
            $result->isApproved()
        );

        $photo->refresh();

        $this->assertSame(
            FacialPhotoStatus::Approved,
            $photo->status
        );

        $this->assertTrue(
            $photo->subject->is(
                $employee
            )
        );

        $this->assertSame(
            EmployeeRecord::class,
            $photo->subject_type
        );

        $this->assertSame(
            $employee->id,
            $photo->subject_id
        );

        Bus::assertDispatched(
            GenerateFacialPhotoDerivativeJob::class,
            static fn (
                GenerateFacialPhotoDerivativeJob $job
            ): bool => $job->photoId
                === $photo->id
                && $job->profile
                    === 'vanguard_normalized'
                && $job->policyVersion
                    === 'vanguard-normalization-v1'
                && $job->normalizer
                    === 'spatie-gd'
                && $job->normalizerVersion
                    === 'spatie-gd-v1'
                && $job->requestedBy
                    === $operator->id
                && $job->requesterName === null
                && $job->connection === 'redis'
                && $job->queue === 'default'
        );
    }

    /**
     * @return array{
     *     employee: EmployeeRecord,
     *     operator: User,
     *     photo: FacialPhotoRecord
     * }
     */
    private function createContext(): array
    {
        $tenant = TenantRecord::query()
            ->create([
                'id' => (string) Str::uuid(),
                'name' => 'GRUPO D2 EMPLOYEE',
                'status' => 'active',
            ]);

        $organization =
            OrganizationRecord::query()
                ->create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'status' => 'active',
                    'legal_name' => 'UNIDADE D2 EMPLOYEE LTDA',
                    'display_name' => 'UNIDADE D2 EMPLOYEE',
                    'unit_code' => 'D2-EMP-01',
                ]);

        $employee = EmployeeRecord::query()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'employee_code' => 'D2-EMP-001',
                'full_name' => 'FUNCIONÁRIO SINTÉTICO D2',
                'status' => 'active',
                'photo_disk' => 'local',
                'photo_path' => 'employees/profile/existing.jpg',
                'photo_uploaded_at' => now()
                    ->subDay()
                    ->startOfSecond(),
                'hired_at' => '2026-01-01',
            ]);

        $operator = User::factory()
            ->create([
                'name' => 'OPERADOR SINTÉTICO D2',
            ]);

        $photo = $employee
            ->facialPhotos()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'created_by' => $operator->id,
                'source' => FacialPhotoSource::Webcam,
                'status' => FacialPhotoStatus::PendingValidation,
                'captured_at' => now(),
            ]);

        $sourcePath = $this->createJpeg(
            'employee-derivative.jpg'
        );

        $media = $photo
            ->copyMedia(
                $sourcePath
            )
            ->usingFileName(
                'employee-derivative.jpg'
            )
            ->toMediaCollection(
                FacialPhotoRecord::ORIGINAL_COLLECTION,
                'facial_photos'
            );

        $mediaPath = $media->getPath();

        $sha256 = hash_file(
            'sha256',
            $mediaPath
        );

        if (! is_string($sha256)) {
            throw new RuntimeException(
                'Não foi possível calcular o hash sintético.'
            );
        }

        $photo->forceFill([
            'analyzed_at' => now(),
            'width' => 32,
            'height' => 32,
            'mime_type' => 'image/jpeg',
            'size_bytes' => filesize(
                $mediaPath
            ),
            'sha256' => $sha256,
            'validation_version' => 'technical-d2-employee-v1',
            'validation_result' => [
                'version' => 'technical-d2-employee-v1',
                'passed' => true,
                'metrics' => [
                    'width' => 32,
                    'height' => 32,
                ],
            ],
            'rejection_reasons' => [],
        ])->save();

        return [
            'employee' => $employee,
            'operator' => $operator,
            'photo' => $photo->fresh(),
        ];
    }

    private function createJpeg(
        string $fileName
    ): string {
        $path = $this->directory
            .DIRECTORY_SEPARATOR
            .$fileName;

        $image = imagecreatetruecolor(
            32,
            32
        );

        if ($image === false) {
            throw new RuntimeException(
                'Não foi possível criar a imagem sintética.'
            );
        }

        $background = imagecolorallocate(
            $image,
            160,
            160,
            160
        );

        imagefilledrectangle(
            $image,
            0,
            0,
            31,
            31,
            $background
        );

        imagejpeg(
            $image,
            $path,
            90
        );

        imagedestroy(
            $image
        );

        return $path;
    }

    private function configureFlow(): void
    {
        config()->set(
            'facial_photos.validation.enabled',
            true
        );

        config()->set(
            'facial_photos.validation.provider',
            'simulator'
        );

        config()->set(
            'facial_photos.validation.simulator.enabled',
            true
        );

        config()->set(
            'facial_photos.validation.simulator.default_scenario',
            'approved'
        );

        config()->set(
            'facial_photos.normalization.enabled',
            true
        );

        config()->set(
            'facial_photos.normalization.async_generation.enabled',
            true
        );

        config()->set(
            'facial_photos.normalization.default_profile',
            'vanguard_normalized'
        );

        config()->set(
            'facial_photos.normalization.policy_version',
            'vanguard-normalization-v1'
        );

        config()->set(
            'facial_photos.normalization.normalizer',
            'spatie-gd'
        );

        config()->set(
            'facial_photos.normalization.normalizer_version',
            'spatie-gd-v1'
        );

        config()->set(
            'facial_photos.normalization.async_generation.queue_connection',
            'redis'
        );

        config()->set(
            'facial_photos.normalization.async_generation.queue',
            'default'
        );
    }
}
