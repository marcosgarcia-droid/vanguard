<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Storage;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\FacialPhotos\Validation\FacialPhotoValidator;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Images\Simulator\SimulatedFacialPhotoValidator;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoValidationAttemptRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Storage\VisitorFacialPhotoCaptureRegistrar;
use GdImage;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

final class VisitorFacialPhotoCaptureRegistrarAfterCommitIntegrationTest extends TestCase
{
    private const CONFIRMATION_KEY =
        'cccccccccccccccccccccccccccccccc'
        .'cccccccccccccccccccccccccccccccc';

    private const CONFIRMATION_CONTEXT =
        'visitor.test.photo_capture';

    use DatabaseMigrations;

    private string $directory;

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();

        $this->artisan(
            'migrate:fresh',
            $this->migrateFreshUsing()
        );

        $this->app[
            Kernel::class
        ]->setArtisan(null);

        $this->afterRefreshingDatabase();

        $this->beforeApplicationDestroyed(
            static function (): void {
                RefreshDatabaseState::$migrated =
                    false;
            }
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('facial_photos');

        $this->directory = storage_path(
            'framework/testing/visitor-facial-after-commit'
        );

        File::deleteDirectory(
            $this->directory
        );

        File::ensureDirectoryExists(
            $this->directory
        );

        $this->configureApprovedValidation();
    }

    protected function tearDown(): void
    {
        $connection = DB::connection();

        while ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }

        File::deleteDirectory(
            $this->directory
        );

        parent::tearDown();
    }

    public function test_it_executes_validation_after_its_own_transaction_commits(): void
    {
        $connection = DB::connection();

        $this->assertSame(
            0,
            $connection->transactionLevel()
        );

        $visitor = $this->createVisitor();

        $operator = User::factory()->create([
            'name' => 'OPERADOR PÓS-COMMIT IMEDIATO',
        ]);

        $upload = $this->checkerboardUpload(
            'foto-facial-camera-pos-commit-imediato.jpg'
        );

        $result = app(
            VisitorFacialPhotoCaptureRegistrar::class
        )->register(
            visitor: $visitor,
            upload: $upload,
            expectedSha256: $this->fingerprintForUpload(
                $upload
            ),
            createdBy: $operator->id,
            confirmationKey: self::CONFIRMATION_KEY,
            confirmationContext: self::CONFIRMATION_CONTEXT,
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $result->status
        );

        $photo = FacialPhotoRecord::query()
            ->findOrFail(
                $result->photoId
            );

        $attempt = FacialPhotoValidationAttemptRecord::query()
            ->where(
                'facial_photo_id',
                $photo->id
            )
            ->sole();

        $this->assertSame(
            FacialPhotoStatus::Approved,
            $photo->status
        );

        $this->assertNotNull(
            $photo->approved_at
        );

        $this->assertNull(
            $photo->rejected_at
        );

        $this->assertSame(
            1,
            $attempt->attempt_number
        );

        $this->assertSame(
            FacialPhotoValidationDecision::Approved,
            $attempt->decision
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $attempt->status_before
        );

        $this->assertSame(
            FacialPhotoStatus::Approved,
            $attempt->status_after
        );

        $this->assertSame(
            SimulatedFacialPhotoValidator::VALIDATOR,
            $attempt->validator
        );

        $this->assertSame(
            SimulatedFacialPhotoValidator::VERSION,
            $attempt->validator_version
        );

        $this->assertSame(
            'approved',
            $attempt->metrics['scenario']
        );

        $this->assertSame(
            $operator->id,
            $attempt->operator_user_id
        );

        $this->assertSame(
            'OPERADOR PÓS-COMMIT IMEDIATO',
            $attempt->operator_name
        );

        $this->assertDatabaseCount(
            'facial_photo_validation_attempts',
            1
        );

        $this->assertSame(
            0,
            $connection->transactionLevel()
        );

        $this->assertFalse(
            $this->app->bound(
                FacialPhotoValidator::class
            )
        );
    }

    public function test_it_waits_for_the_surrounding_transaction_commit_before_validating(): void
    {
        $connection = DB::connection();

        $startingTransactionLevel =
            $connection->transactionLevel();

        $this->assertSame(
            0,
            $startingTransactionLevel
        );

        $visitor = $this->createVisitor();

        $operator = User::factory()->create([
            'name' => 'OPERADOR PÓS-COMMIT EXTERNO',
        ]);

        $upload = $this->checkerboardUpload(
            'foto-facial-camera-pos-commit-externo.jpg'
        );

        $committed = false;
        $legacyPath = null;
        $facialPath = null;
        $result = null;

        $connection->beginTransaction();

        try {
            $result = app(
                VisitorFacialPhotoCaptureRegistrar::class
            )->register(
                visitor: $visitor,
                upload: $upload,
                expectedSha256: $this->fingerprintForUpload(
                    $upload
                ),
                createdBy: $operator->id,
                confirmationKey: self::CONFIRMATION_KEY,
                confirmationContext: self::CONFIRMATION_CONTEXT,
            );

            $this->assertSame(
                FacialPhotoStatus::PendingValidation,
                $result->status
            );

            $photo = FacialPhotoRecord::query()
                ->findOrFail(
                    $result->photoId
                );

            $this->assertSame(
                FacialPhotoStatus::PendingValidation,
                $photo->status
            );

            $this->assertDatabaseCount(
                'facial_photo_validation_attempts',
                0
            );

            $visitor->refresh();

            $legacyPath = $visitor->photo_path;

            $this->assertIsString(
                $legacyPath
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

            Storage::disk('local')
                ->assertExists(
                    $legacyPath
                );

            Storage::disk('facial_photos')
                ->assertExists(
                    $facialPath
                );

            $this->assertSame(
                $startingTransactionLevel + 1,
                $connection->transactionLevel()
            );

            $connection->commit();

            $committed = true;
        } finally {
            if (
                ! $committed
                && $connection->transactionLevel()
                    > $startingTransactionLevel
            ) {
                $connection->rollBack(
                    $startingTransactionLevel
                );
            }
        }

        $this->assertNotNull(
            $result
        );

        $this->assertSame(
            $startingTransactionLevel,
            $connection->transactionLevel()
        );

        $photo = FacialPhotoRecord::query()
            ->findOrFail(
                $result->photoId
            );

        $attempt = FacialPhotoValidationAttemptRecord::query()
            ->where(
                'facial_photo_id',
                $photo->id
            )
            ->sole();

        $this->assertSame(
            FacialPhotoStatus::Approved,
            $photo->status
        );

        $this->assertNotNull(
            $photo->approved_at
        );

        $this->assertSame(
            FacialPhotoValidationDecision::Approved,
            $attempt->decision
        );

        $this->assertSame(
            $operator->id,
            $attempt->operator_user_id
        );

        $this->assertDatabaseCount(
            'facial_photo_validation_attempts',
            1
        );

        $this->assertIsString(
            $legacyPath
        );

        $this->assertIsString(
            $facialPath
        );

        Storage::disk('local')
            ->assertExists(
                $legacyPath
            );

        Storage::disk('facial_photos')
            ->assertExists(
                $facialPath
            );
    }

    public function test_it_cancels_validation_and_compensates_files_when_the_surrounding_transaction_rolls_back(): void
    {
        $connection = DB::connection();

        $startingTransactionLevel =
            $connection->transactionLevel();

        $this->assertSame(
            0,
            $startingTransactionLevel
        );

        $visitor = $this->createVisitor();

        $operator = User::factory()->create([
            'name' => 'OPERADOR ROLLBACK PÓS-COMMIT',
        ]);

        $upload = $this->checkerboardUpload(
            'foto-facial-camera-pos-commit-rollback.jpg'
        );

        $rolledBack = false;
        $legacyPath = null;
        $facialPath = null;
        $result = null;

        $connection->beginTransaction();

        try {
            $result = app(
                VisitorFacialPhotoCaptureRegistrar::class
            )->register(
                visitor: $visitor,
                upload: $upload,
                expectedSha256: $this->fingerprintForUpload(
                    $upload
                ),
                createdBy: $operator->id,
                confirmationKey: self::CONFIRMATION_KEY,
                confirmationContext: self::CONFIRMATION_CONTEXT,
            );

            $this->assertSame(
                FacialPhotoStatus::PendingValidation,
                $result->status
            );

            $photo = FacialPhotoRecord::query()
                ->findOrFail(
                    $result->photoId
                );

            $this->assertSame(
                FacialPhotoStatus::PendingValidation,
                $photo->status
            );

            $this->assertDatabaseCount(
                'facial_photo_validation_attempts',
                0
            );

            $visitor->refresh();

            $legacyPath = $visitor->photo_path;

            $this->assertIsString(
                $legacyPath
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

            Storage::disk('local')
                ->assertExists(
                    $legacyPath
                );

            Storage::disk('facial_photos')
                ->assertExists(
                    $facialPath
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

        $this->assertNotNull(
            $result
        );

        $this->assertSame(
            $startingTransactionLevel,
            $connection->transactionLevel()
        );

        $visitor->refresh();

        $this->assertNull(
            $visitor->photo_path
        );

        $this->assertNull(
            $visitor->photo_uploaded_at
        );

        $this->assertNull(
            FacialPhotoRecord::query()
                ->find(
                    $result->photoId
                )
        );

        $this->assertDatabaseCount(
            'facial_photos',
            0
        );

        $this->assertDatabaseCount(
            'media',
            0
        );

        $this->assertDatabaseCount(
            'facial_photo_validation_attempts',
            0
        );

        $this->assertIsString(
            $legacyPath
        );

        $this->assertIsString(
            $facialPath
        );

        Storage::disk('local')
            ->assertMissing(
                $legacyPath
            );

        Storage::disk('facial_photos')
            ->assertMissing(
                $facialPath
            );
    }

    private function configureApprovedValidation(): void
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
    }

    private function createVisitor(): VisitorRecord
    {
        $tenant = TenantRecord::query()
            ->create([
                'id' => (string) Str::uuid(),
                'name' => 'GRUPO PÓS-COMMIT FACIAL',
                'status' => 'active',
            ]);

        $organization =
            OrganizationRecord::query()
                ->create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'status' => 'active',
                    'legal_name' => 'UNIDADE PÓS-COMMIT FACIAL LTDA',
                    'display_name' => 'UNIDADE PÓS-COMMIT FACIAL',
                    'unit_code' => 'FAC-PC-01',
                ]);

        return VisitorRecord::query()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'full_name' => 'VISITANTE PÓS-COMMIT FACIAL',
                'status' => VisitorStatus::Active,
                'photo_disk' => 'local',
                'photo_path' => null,
            ]);
    }

    private function fingerprintForUpload(
        UploadedFile $upload
    ): string {
        $absolutePath =
            $upload->getRealPath();

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
