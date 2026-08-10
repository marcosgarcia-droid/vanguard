<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoCommand;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoException;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoRepository;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoUseCase;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSubjectType;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentRegisterFacialPhotoRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoConfirmationConsumptionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use GdImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EloquentRegisterFacialPhotoRepositoryEmployeeTest extends TestCase
{
    use RefreshDatabase;

    private const FIRST_CONFIRMATION_KEY =
        'dddddddddddddddddddddddddddddddd'
        .'dddddddddddddddddddddddddddddddd';

    private const REPLAY_CONFIRMATION_KEY =
        'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee'
        .'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('facial_photos');

        $this->directory = storage_path(
            'framework/testing/employee-facial-photo-registration'
        );

        File::deleteDirectory($this->directory);
        File::ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_generic_repository_binding_resolves_to_eloquent_implementation(): void
    {
        self::assertInstanceOf(
            EloquentRegisterFacialPhotoRepository::class,
            app(RegisterFacialPhotoRepository::class)
        );
    }

    public function test_active_employee_receives_polymorphic_facial_photo_and_immutable_consumption(): void
    {
        [$employee, $user] = $this->employee(
            status: 'active',
            code: 'EMP-FACE-001'
        );

        $sourcePath = $this->jpeg(
            'employee-face.jpg'
        );

        $originalProfile = [
            'disk' => $employee->photo_disk,
            'path' => $employee->photo_path,
            'uploaded_at' => $employee->photo_uploaded_at
                ?->toDateTimeString(),
        ];

        $result = app(
            RegisterFacialPhotoUseCase::class
        )->execute(
            new RegisterFacialPhotoCommand(
                subjectType: FacialPhotoSubjectType::Employee,
                subjectId: $employee->id,
                absolutePath: $sourcePath,
                originalFileName: 'funcionario-biometria.jpg',
                expectedSha256: $this->fingerprint(
                    $sourcePath
                ),
                source: FacialPhotoSource::Webcam,
                confirmationKey: self::FIRST_CONFIRMATION_KEY,
                confirmationContext: 'employee.update.'
                    .$employee->id
                    .'.photo_capture',
                createdBy: $user->id,
            )
        );

        $photo = FacialPhotoRecord::query()
            ->findOrFail($result->photoId);

        self::assertSame(
            EmployeeRecord::class,
            $photo->subject_type
        );

        self::assertSame(
            $employee->id,
            $photo->subject_id
        );

        self::assertTrue(
            $photo->subject->is($employee)
        );

        self::assertTrue(
            $employee
                ->facialPhotos()
                ->sole()
                ->is($photo)
        );

        $consumption =
            FacialPhotoConfirmationConsumptionRecord::query()
                ->sole();

        self::assertSame(
            EmployeeRecord::class,
            $consumption->subject_type
        );

        self::assertSame(
            $employee->id,
            $consumption->subject_id
        );

        self::assertNull(
            $consumption->visitor_id
        );

        self::assertTrue(
            $consumption->subject->is($employee)
        );

        self::assertSame(
            $employee->tenant_id,
            $consumption->tenant_id
        );

        self::assertSame(
            $employee->organization_id,
            $consumption->organization_id
        );

        self::assertSame(
            self::FIRST_CONFIRMATION_KEY,
            $consumption->confirmation_key
        );

        self::assertSame(
            $photo->sha256,
            $consumption->photo_sha256
        );

        $employee->refresh();

        self::assertSame(
            $originalProfile['disk'],
            $employee->photo_disk
        );

        self::assertSame(
            $originalProfile['path'],
            $employee->photo_path
        );

        self::assertSame(
            $originalProfile['uploaded_at'],
            $employee->photo_uploaded_at
                ?->toDateTimeString()
        );
    }

    public function test_inactive_employee_is_rejected_without_persistence(): void
    {
        [$employee, $user] = $this->employee(
            status: 'inactive',
            code: 'EMP-FACE-INACTIVE'
        );

        $sourcePath = $this->jpeg(
            'inactive.jpg'
        );

        try {
            app(RegisterFacialPhotoUseCase::class)
                ->execute(
                    new RegisterFacialPhotoCommand(
                        subjectType: FacialPhotoSubjectType::Employee,
                        subjectId: $employee->id,
                        absolutePath: $sourcePath,
                        originalFileName: 'inactive.jpg',
                        expectedSha256: $this->fingerprint(
                            $sourcePath
                        ),
                        source: FacialPhotoSource::FileUpload,
                        confirmationKey: str_repeat('f', 64),
                        confirmationContext: 'employee.update.'
                            .$employee->id
                            .'.photo_capture',
                        createdBy: $user->id,
                    )
                );

            self::fail(
                'Employee inativo não deveria aceitar biometria.'
            );
        } catch (RegisterFacialPhotoException $exception) {
            self::assertSame(
                'A pessoa informada não está disponível para cadastro facial.',
                $exception->getMessage()
            );
        }

        self::assertSame(
            0,
            FacialPhotoRecord::query()->count()
        );

        self::assertSame(
            0,
            FacialPhotoConfirmationConsumptionRecord::query()
                ->count()
        );

        self::assertSame(
            [],
            Storage::disk('facial_photos')->allFiles()
        );
    }

    public function test_missing_employee_is_rejected_without_persistence(): void
    {
        $sourcePath = $this->jpeg(
            'missing.jpg'
        );

        try {
            app(RegisterFacialPhotoUseCase::class)
                ->execute(
                    new RegisterFacialPhotoCommand(
                        subjectType: FacialPhotoSubjectType::Employee,
                        subjectId: (string) Str::uuid(),
                        absolutePath: $sourcePath,
                        originalFileName: 'missing.jpg',
                        expectedSha256: $this->fingerprint(
                            $sourcePath
                        ),
                        source: FacialPhotoSource::FileUpload,
                        confirmationKey: str_repeat('1', 64),
                        confirmationContext: 'employee.update.missing.photo_capture',
                    )
                );

            self::fail(
                'Employee inexistente não deveria aceitar biometria.'
            );
        } catch (RegisterFacialPhotoException $exception) {
            self::assertSame(
                'A pessoa informada para a foto facial não foi encontrada.',
                $exception->getMessage()
            );
        }

        self::assertSame(
            0,
            FacialPhotoRecord::query()->count()
        );

        self::assertSame(
            0,
            FacialPhotoConfirmationConsumptionRecord::query()
                ->count()
        );
    }

    public function test_same_confirmation_cannot_be_replayed_for_another_employee(): void
    {
        [$firstEmployee, $user] = $this->employee(
            status: 'active',
            code: 'EMP-REPLAY-001'
        );

        [$secondEmployee] = $this->employee(
            status: 'active',
            code: 'EMP-REPLAY-002'
        );

        $firstPath = $this->jpeg(
            'first-replay.jpg'
        );

        $secondPath = $this->jpeg(
            'second-replay.jpg',
            true
        );

        $useCase = app(
            RegisterFacialPhotoUseCase::class
        );

        $useCase->execute(
            new RegisterFacialPhotoCommand(
                subjectType: FacialPhotoSubjectType::Employee,
                subjectId: $firstEmployee->id,
                absolutePath: $firstPath,
                originalFileName: 'first-replay.jpg',
                expectedSha256: $this->fingerprint(
                    $firstPath
                ),
                source: FacialPhotoSource::FileUpload,
                confirmationKey: self::REPLAY_CONFIRMATION_KEY,
                confirmationContext: 'employee.update.'
                    .$firstEmployee->id
                    .'.photo_capture',
                createdBy: $user->id,
            )
        );

        try {
            $useCase->execute(
                new RegisterFacialPhotoCommand(
                    subjectType: FacialPhotoSubjectType::Employee,
                    subjectId: $secondEmployee->id,
                    absolutePath: $secondPath,
                    originalFileName: 'second-replay.jpg',
                    expectedSha256: $this->fingerprint(
                        $secondPath
                    ),
                    source: FacialPhotoSource::FileUpload,
                    confirmationKey: self::REPLAY_CONFIRMATION_KEY,
                    confirmationContext: 'employee.update.'
                        .$secondEmployee->id
                        .'.photo_capture',
                    createdBy: $user->id,
                )
            );

            self::fail(
                'A mesma confirmação não pode ser reutilizada.'
            );
        } catch (RegisterFacialPhotoException $exception) {
            self::assertTrue(
                $exception->isConfirmationAlreadyConsumed()
            );
        }

        self::assertSame(
            1,
            FacialPhotoRecord::query()->count()
        );

        self::assertSame(
            1,
            FacialPhotoConfirmationConsumptionRecord::query()
                ->count()
        );

        self::assertSame(
            0,
            $secondEmployee->facialPhotos()->count()
        );

        self::assertCount(
            1,
            Storage::disk('facial_photos')->allFiles()
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
                'name' => 'GRUPO EMPLOYEE FACE '.$code,
                'status' => 'active',
            ]);

        $organization = OrganizationRecord::query()
            ->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE EMPLOYEE FACE '.$code,
                'display_name' => 'EMPLOYEE FACE '.$code,
                'unit_code' => $code,
            ]);

        $employee = EmployeeRecord::query()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'employee_code' => $code,
                'full_name' => 'FUNCIONÁRIO FACIAL '.$code,
                'status' => $status,
                'photo_disk' => 'local',
                'photo_path' => 'employees/profile/existing.jpg',
                'photo_uploaded_at' => now()
                    ->subDay()
                    ->startOfSecond(),
                'hired_at' => '2026-01-01',
            ]);

        $user = User::factory()->create();

        return [$employee, $user];
    }

    private function fingerprint(
        string $absolutePath
    ): string {
        $fingerprint = hash_file(
            'sha256',
            $absolutePath
        );

        self::assertIsString(
            $fingerprint
        );

        return $fingerprint;
    }

    private function jpeg(
        string $filename,
        bool $invert = false
    ): string {
        $width = 720;
        $height = 900;

        $image = imagecreatetruecolor(
            $width,
            $height
        );

        self::assertInstanceOf(
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

        for ($y = 0; $y < $height; $y += $block) {
            for ($x = 0; $x < $width; $x += $block) {
                $alternate = (
                    intdiv($x, $block)
                    + intdiv($y, $block)
                ) % 2 === 0;

                if ($invert) {
                    $alternate = ! $alternate;
                }

                imagefilledrectangle(
                    $image,
                    $x,
                    $y,
                    min($x + $block - 1, $width - 1),
                    min($y + $block - 1, $height - 1),
                    $alternate ? $dark : $light
                );
            }
        }

        $path = $this->directory
            .DIRECTORY_SEPARATOR
            .$filename;

        self::assertTrue(
            imagejpeg(
                $image,
                $path,
                92
            )
        );

        imagedestroy($image);

        return $path;
    }
}
