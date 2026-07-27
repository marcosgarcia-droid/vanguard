<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Storage;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoException;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoResult;
use App\Modules\Operations\Application\FacialPhotos\TechnicalAnalysis\FacialPhotoTechnicalAnalyzer;
use App\Modules\Operations\Application\FacialPhotos\Validation\Schedule\FacialPhotoValidationAfterCommitScheduler;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalAnalysis;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Storage\VisitorFacialPhotoCaptureRegistrar;
use GdImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

final class VisitorFacialPhotoCaptureRegistrarTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('facial_photos');

        $this->directory = storage_path(
            'framework/testing/visitor-facial-capture'
        );

        File::deleteDirectory(
            $this->directory
        );

        File::ensureDirectoryExists(
            $this->directory
        );
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(
            $this->directory
        );

        parent::tearDown();
    }

    public function test_it_persists_webcam_photo_in_legacy_and_facial_storage(): void
    {
        $visitor = $this->createVisitor();

        $user = User::factory()->create();

        $upload = $this->checkerboardUpload(
            'visitante-camera-1721840400000.jpg'
        );

        $result = app(
            VisitorFacialPhotoCaptureRegistrar::class
        )->register(
            visitor: $visitor,
            upload: $upload,
            createdBy: $user->id,
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $result->status
        );

        $visitor->refresh();

        $this->assertSame(
            'local',
            $visitor->photo_disk
        );

        $this->assertNotNull(
            $visitor->photo_path
        );

        $this->assertNotNull(
            $visitor->photo_uploaded_at
        );

        Storage::disk('local')
            ->assertExists(
                $visitor->photo_path
            );

        $photo = FacialPhotoRecord::query()
            ->findOrFail($result->photoId);

        $this->assertSame(
            FacialPhotoSource::Webcam,
            $photo->source
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $photo->status
        );

        $this->assertSame(
            $user->id,
            $photo->created_by
        );

        $this->assertSame(
            $visitor->photo_uploaded_at?->format(
                'Y-m-d H:i:s'
            ),
            $photo->captured_at?->format(
                'Y-m-d H:i:s'
            )
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
    }

    public function test_it_schedules_additional_validation_with_operator_context(): void
    {
        $visitor = $this->createVisitor();

        $user = User::factory()->create();

        $upload = $this->checkerboardUpload(
            'visitante-agendamento-facial.jpg'
        );

        $scheduler =
            new VisitorFacialPhotoValidationSchedulerSpy;

        app()->instance(
            FacialPhotoValidationAfterCommitScheduler::class,
            $scheduler
        );

        $result = app(
            VisitorFacialPhotoCaptureRegistrar::class
        )->register(
            visitor: $visitor,
            upload: $upload,
            createdBy: $user->id,
        );

        $this->assertSame(
            1,
            $scheduler->calls
        );

        $this->assertSame(
            $result,
            $scheduler->registration
        );

        $this->assertSame(
            $result->photoId,
            $scheduler->registration?->photoId
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $scheduler->registration?->status
        );

        $this->assertSame(
            $user->id,
            $scheduler->operatorUserId
        );

        $this->assertTrue(
            $result->awaitsAdditionalValidation()
        );
    }

    public function test_it_preserves_registration_when_the_scheduler_returns_false(): void
    {
        $visitor = $this->createVisitor();

        $user = User::factory()->create();

        $upload = $this->checkerboardUpload(
            'visitante-agendamento-indisponivel.jpg'
        );

        $scheduler =
            new VisitorFacialPhotoValidationSchedulerSpy;

        $scheduler->scheduled = false;

        app()->instance(
            FacialPhotoValidationAfterCommitScheduler::class,
            $scheduler
        );

        $result = app(
            VisitorFacialPhotoCaptureRegistrar::class
        )->register(
            visitor: $visitor,
            upload: $upload,
            createdBy: $user->id,
        );

        $photo = FacialPhotoRecord::query()
            ->findOrFail(
                $result->photoId
            );

        $visitor->refresh();

        $media = $photo->getFirstMedia(
            FacialPhotoRecord::ORIGINAL_COLLECTION
        );

        $this->assertSame(
            1,
            $scheduler->calls
        );

        $this->assertFalse(
            $scheduler->scheduled
        );

        $this->assertSame(
            $result,
            $scheduler->registration
        );

        $this->assertSame(
            $result->photoId,
            $scheduler->registration?->photoId
        );

        $this->assertSame(
            $user->id,
            $scheduler->operatorUserId
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $result->status
        );

        $this->assertTrue(
            $result->awaitsAdditionalValidation()
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $photo->status
        );

        $this->assertIsString(
            $visitor->photo_path
        );

        Storage::disk('local')
            ->assertExists(
                $visitor->photo_path
            );

        $this->assertInstanceOf(
            Media::class,
            $media
        );

        Storage::disk('facial_photos')
            ->assertExists(
                $media->getPathRelativeToRoot()
            );
    }

    public function test_it_identifies_a_selected_file_as_file_upload(): void
    {
        $visitor = $this->createVisitor();

        $upload = $this->checkerboardUpload(
            'foto-selecionada.jpg'
        );

        $result = app(
            VisitorFacialPhotoCaptureRegistrar::class
        )->register(
            visitor: $visitor,
            upload: $upload,
        );

        $photo = FacialPhotoRecord::query()
            ->findOrFail($result->photoId);

        $this->assertSame(
            FacialPhotoSource::FileUpload,
            $photo->source
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $photo->status
        );

        $visitor->refresh();

        Storage::disk('local')
            ->assertExists(
                $visitor->photo_path
            );
    }

    public function test_it_rolls_back_visitor_fields_and_both_files_when_analysis_fails(): void
    {
        $visitor = $this->createVisitor();

        $upload = $this->checkerboardUpload(
            'visitante-camera-failure.jpg'
        );

        $scheduler =
            new VisitorFacialPhotoValidationSchedulerSpy;

        app()->instance(
            FacialPhotoValidationAfterCommitScheduler::class,
            $scheduler
        );

        app()->instance(
            FacialPhotoTechnicalAnalyzer::class,
            new class implements FacialPhotoTechnicalAnalyzer
            {
                public function analyze(
                    string $absolutePath
                ): FacialPhotoTechnicalAnalysis {
                    throw new RuntimeException(
                        'Falha sintética da análise.'
                    );
                }
            }
        );

        try {
            app(
                VisitorFacialPhotoCaptureRegistrar::class
            )->register(
                visitor: $visitor,
                upload: $upload,
            );

            $this->fail(
                'A falha da análise deveria impedir o registro.'
            );
        } catch (
            RegisterVisitorFacialPhotoException $exception
        ) {
            $this->assertSame(
                'Não foi possível registrar e analisar a foto facial.',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            0,
            $scheduler->calls
        );

        $this->assertNull(
            $scheduler->registration
        );

        $visitor->refresh();

        $this->assertNull(
            $visitor->photo_path
        );

        $this->assertNull(
            $visitor->photo_uploaded_at
        );

        $this->assertDatabaseCount(
            'facial_photos',
            0
        );

        $this->assertDatabaseCount(
            'media',
            0
        );

        $this->assertSame(
            [],
            Storage::disk('local')
                ->allFiles('visitors/photos')
        );

        $this->assertSame(
            [],
            Storage::disk('facial_photos')
                ->allFiles()
        );
    }

    public function test_it_compensates_both_files_when_surrounding_transaction_rolls_back(): void
    {
        $visitor = $this->createVisitor();

        $upload = $this->checkerboardUpload(
            'visitante-camera-outer-rollback.jpg'
        );

        $connection = DB::connection();

        $startingTransactionLevel =
            $connection->transactionLevel();

        $legacyPath = null;
        $facialPath = null;
        $rolledBack = false;

        $connection->beginTransaction();

        try {
            $result = app(
                VisitorFacialPhotoCaptureRegistrar::class
            )->register(
                visitor: $visitor,
                upload: $upload,
            );

            $visitor->refresh();

            $legacyPath = $visitor->photo_path;

            $this->assertIsString(
                $legacyPath
            );

            $photo = FacialPhotoRecord::query()
                ->findOrFail($result->photoId);

            $media = $photo->getFirstMedia(
                FacialPhotoRecord::ORIGINAL_COLLECTION
            );

            $this->assertInstanceOf(
                Media::class,
                $media
            );

            $facialPath =
                $media->getPathRelativeToRoot();

            Storage::disk('local')
                ->assertExists($legacyPath);

            Storage::disk('facial_photos')
                ->assertExists($facialPath);

            $this->assertDatabaseCount(
                'facial_photos',
                1
            );

            $this->assertDatabaseCount(
                'media',
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

        $visitor->refresh();

        $this->assertNull(
            $visitor->photo_path
        );

        $this->assertNull(
            $visitor->photo_uploaded_at
        );

        $this->assertDatabaseCount(
            'facial_photos',
            0
        );

        $this->assertDatabaseCount(
            'media',
            0
        );

        $this->assertIsString(
            $legacyPath
        );

        $this->assertIsString(
            $facialPath
        );

        Storage::disk('local')
            ->assertMissing($legacyPath);

        Storage::disk('facial_photos')
            ->assertMissing($facialPath);
    }

    private function createVisitor(): VisitorRecord
    {
        $tenant = TenantRecord::query()
            ->create([
                'id' => (string) Str::uuid(),
                'name' => 'GRUPO CAPTURA FACIAL',
                'status' => 'active',
            ]);

        $organization =
            OrganizationRecord::query()
                ->create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'status' => 'active',
                    'legal_name' => 'UNIDADE CAPTURA FACIAL LTDA',
                    'display_name' => 'UNIDADE CAPTURA FACIAL',
                    'unit_code' => 'FAC-CAP-01',
                ]);

        return VisitorRecord::query()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'full_name' => 'VISITANTE CAPTURA FACIAL',
                'status' => VisitorStatus::Active,
                'photo_disk' => 'local',
                'photo_path' => null,
            ]);
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
                $color = (
                    (
                        intdiv($x, $block)
                        + intdiv($y, $block)
                    ) % 2 === 0
                )
                    ? $dark
                    : $light;

                imagefilledrectangle(
                    $image,
                    $x,
                    $y,
                    min(
                        $width - 1,
                        $x + $block - 1
                    ),
                    min(
                        $height - 1,
                        $y + $block - 1
                    ),
                    $color
                );
            }
        }

        $path = $this->directory
            .'/'
            .Str::uuid()
            .'.jpg';

        $this->assertTrue(
            imagejpeg(
                $image,
                $path,
                92
            )
        );

        imagedestroy($image);

        return new UploadedFile(
            path: $path,
            originalName: $originalFileName,
            mimeType: 'image/jpeg',
            error: null,
            test: true,
        );
    }
}

final class VisitorFacialPhotoValidationSchedulerSpy implements FacialPhotoValidationAfterCommitScheduler
{
    public bool $scheduled = true;

    public int $calls = 0;

    public ?RegisterVisitorFacialPhotoResult $registration =
        null;

    public ?int $operatorUserId = null;

    public function schedule(
        RegisterVisitorFacialPhotoResult $registration,
        ?int $operatorUserId = null,
    ): bool {
        $this->calls++;
        $this->registration = $registration;
        $this->operatorUserId = $operatorUserId;

        return $this->scheduled;
    }
}
