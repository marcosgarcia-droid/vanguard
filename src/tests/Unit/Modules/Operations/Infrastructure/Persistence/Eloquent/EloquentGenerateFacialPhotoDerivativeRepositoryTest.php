<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\FacialPhotoDerivativeGenerator;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeCommand;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeException;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationResult;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizer;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeAttemptStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeProfile;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoDerivativeRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EloquentGenerateFacialPhotoDerivativeRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(
            'facial_photos'
        );

        config()->set(
            'cache.default',
            'array'
        );

        Cache::store('array')->flush();

        $this->directory = storage_path(
            'framework/testing/facial-photo-derivative-generation'
        );

        File::deleteDirectory(
            $this->directory
        );

        File::ensureDirectoryExists(
            $this->directory
        );

        config()->set(
            'facial_photos.normalization.enabled',
            true
        );

        config()->set(
            'facial_photos.normalization.temporary_directory',
            $this->directory.'/normalized'
        );

        config()->set(
            'facial_photos.normalization.async_generation.lock_seconds',
            300
        );
    }

    protected function tearDown(): void
    {

        File::deleteDirectory(
            $this->directory
        );

        parent::tearDown();
    }

    public function test_it_persists_a_private_derivative_and_reuses_it_idempotently(): void
    {
        [$photo] = $this->approvedPhoto();

        $first = $this->generator()->execute(
            $this->command($photo)
        );

        $this->assertFalse(
            $first->reused
        );

        $this->assertSame(
            FacialPhotoDerivativeStatus::Ready,
            $first->status
        );

        $derivative =
            FacialPhotoDerivativeRecord::query()
                ->sole();

        $this->assertSame(
            FacialPhotoDerivativeStatus::Ready,
            $derivative->status
        );

        $this->assertNotNull(
            $derivative->media
        );

        $this->assertFileExists(
            $derivative->media->getPath()
        );

        $this->assertSame(
            FacialPhotoDerivativeAttemptStatus::Succeeded,
            $derivative->attempts()->sole()->status
        );

        $second = $this->generator()->execute(
            $this->command($photo)
        );

        $this->assertTrue(
            $second->reused
        );

        $this->assertSame(
            $first->derivativeId,
            $second->derivativeId
        );

        $this->assertDatabaseCount(
            'facial_photo_derivatives',
            1
        );

        $this->assertDatabaseCount(
            'facial_photo_derivative_attempts',
            1
        );

        $this->assertDatabaseCount(
            'media',
            2
        );

        if (
            is_dir(
                $this->directory.'/normalized'
            )
        ) {
            $this->assertSame(
                [],
                File::files(
                    $this->directory.'/normalized'
                )
            );
        }
    }

    public function test_a_failure_preserves_the_previous_ready_derivative(): void
    {
        [$photo] = $this->approvedPhoto();

        $ready = $this->generator()->execute(
            $this->command($photo)
        );

        $readyMediaPath =
            FacialPhotoDerivativeRecord::query()
                ->findOrFail(
                    $ready->derivativeId
                )
                ->media
                ->getPath();

        config()->set(
            'facial_photos.normalization.enabled',
            false
        );

        try {
            $this->generator()->execute(
                $this->command(
                    $photo,
                    'vanguard-normalization-v2'
                )
            );

            $this->fail(
                'A normalização desativada deveria falhar.'
            );
        } catch (
            GenerateFacialPhotoDerivativeException $exception
        ) {
            $this->assertSame(
                'normalization_disabled',
                $exception->failureCode
            );
        }

        $first = FacialPhotoDerivativeRecord::query()
            ->where(
                'policy_version',
                'vanguard-normalization-v1'
            )
            ->sole();

        $failed = FacialPhotoDerivativeRecord::query()
            ->where(
                'policy_version',
                'vanguard-normalization-v2'
            )
            ->sole();

        $this->assertSame(
            FacialPhotoDerivativeStatus::Ready,
            $first->status
        );

        $this->assertSame(
            FacialPhotoDerivativeStatus::Failed,
            $failed->status
        );

        $this->assertFileExists(
            $readyMediaPath
        );
    }

    public function test_it_rejects_an_unapproved_or_changed_original(): void
    {
        [$photo, $media] = $this->approvedPhoto();

        $photo->forceFill([
            'status' => FacialPhotoStatus::Rejected->value,
        ])->save();

        $this->assertGenerationFailure(
            'photo_not_approved',
            fn () => $this->generator()->execute(
                $this->command($photo)
            )
        );

        $photo->forceFill([
            'status' => FacialPhotoStatus::Approved->value,
        ])->save();

        file_put_contents(
            $media->getPath(),
            'alterado',
            FILE_APPEND
        );

        $this->assertGenerationFailure(
            'source_changed',
            fn () => $this->generator()->execute(
                $this->command($photo)
            )
        );

        $this->assertDatabaseCount(
            'facial_photo_derivatives',
            0
        );
    }

    public function test_it_compensates_a_persisted_media_mismatch(): void
    {
        [$photo] = $this->approvedPhoto();

        app()->instance(
            FacialPhotoNormalizer::class,
            new class($this->directory) implements FacialPhotoNormalizer
            {
                public function __construct(
                    private readonly string $directory
                ) {}

                public function normalize(
                    string $absoluteSourcePath
                ): FacialPhotoNormalizationResult {
                    $output =
                        $this->directory
                        .'/mismatch-output.jpg';

                    copy(
                        $absoluteSourcePath,
                        $output
                    );

                    $information = getimagesize(
                        $output
                    );

                    return new FacialPhotoNormalizationResult(
                        absolutePath: $output,
                        profile: FacialPhotoDerivativeProfile::vanguardNormalized(),
                        policyVersion: 'vanguard-normalization-v1',
                        normalizer: 'spatie-gd',
                        normalizerVersion: 'spatie-gd-v1',
                        sourceSha256: (string) hash_file(
                            'sha256',
                            $absoluteSourcePath
                        ),
                        width: (int) ($information[0] ?? 0),
                        height: (int) ($information[1] ?? 0),
                        mimeType: 'image/jpeg',
                        sizeBytes: (int) filesize($output),
                        sha256: str_repeat('f', 64),
                    );
                }
            }
        );

        $this->assertGenerationFailure(
            'persisted_artifact_mismatch',
            fn () => $this->generator()->execute(
                $this->command($photo)
            )
        );

        $derivative =
            FacialPhotoDerivativeRecord::query()
                ->sole();

        $this->assertSame(
            FacialPhotoDerivativeStatus::Failed,
            $derivative->status
        );

        $this->assertSame(
            FacialPhotoDerivativeAttemptStatus::Failed,
            $derivative->attempts()->sole()->status
        );

        $this->assertDatabaseCount(
            'media',
            1
        );

        $this->assertCount(
            1,
            Storage::disk('facial_photos')
                ->allFiles()
        );
    }

    private function createVisitorContext(): array
    {
        $tenant = TenantRecord::query()
            ->create([
                'id' => (string) Str::uuid(),
                'name' => 'GRUPO FOTO FACIAL SINTÉTICO',
                'status' => 'active',
            ]);

        $organization =
            OrganizationRecord::query()
                ->create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'status' => 'active',
                    'legal_name' => 'UNIDADE FOTO FACIAL LTDA',
                    'display_name' => 'UNIDADE FOTO FACIAL',
                    'unit_code' => 'FAC-PHOTO-01',
                ]);

        $visitor = VisitorRecord::query()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'full_name' => 'VISITANTE FOTO FACIAL',
                'status' => VisitorStatus::Active,
                'photo_disk' => 'local',
                'photo_path' => null,
            ]);

        $user = User::factory()->create();

        return [$visitor, $user];
    }

    /**
     * @return array{FacialPhotoRecord, mixed}
     */
    private function approvedPhoto(): array
    {
        [$visitor] = $this->createVisitorContext();

        $source = $this->directory
            .'/source-'
            .Str::random(8)
            .'.jpg';

        $image = imagecreatetruecolor(
            600,
            900
        );

        $this->assertNotFalse(
            $image
        );

        $background = imagecolorallocate(
            $image,
            170,
            170,
            170
        );

        imagefilledrectangle(
            $image,
            0,
            0,
            599,
            899,
            $background
        );

        $this->assertTrue(
            imagejpeg(
                $image,
                $source,
                90
            )
        );

        imagedestroy(
            $image
        );

        $photo = FacialPhotoRecord::query()
            ->create([
                'tenant_id' => $visitor->tenant_id,
                'organization_id' => $visitor->organization_id,
                'subject_type' => VisitorRecord::class,
                'subject_id' => $visitor->getKey(),
                'created_by' => null,
                'source' => FacialPhotoSource::Webcam->value,
                'status' => FacialPhotoStatus::Approved->value,
                'captured_at' => now(),
                'analyzed_at' => now(),
                'approved_at' => now(),
                'validation_version' => 'synthetic-test-v1',
                'validation_result' => [],
                'rejection_reasons' => [],
            ]);

        $media = $photo
            ->copyMedia($source)
            ->usingName('original-sintetico')
            ->usingFileName('original-sintetico.jpg')
            ->toMediaCollection(
                FacialPhotoRecord::ORIGINAL_COLLECTION,
                'facial_photos'
            );

        $information = getimagesize(
            $media->getPath()
        );

        $photo->forceFill([
            'width' => (int) ($information[0] ?? 0),
            'height' => (int) ($information[1] ?? 0),
            'mime_type' => 'image/jpeg',
            'size_bytes' => (int) filesize(
                $media->getPath()
            ),
            'sha256' => (string) hash_file(
                'sha256',
                $media->getPath()
            ),
        ])->save();

        return [
            $photo->fresh(),
            $media,
        ];
    }

    private function command(
        FacialPhotoRecord $photo,
        string $policyVersion =
            'vanguard-normalization-v1'
    ): GenerateFacialPhotoDerivativeCommand {
        return new GenerateFacialPhotoDerivativeCommand(
            photoId: (string) $photo->getKey(),
            profile: 'vanguard_normalized',
            policyVersion: $policyVersion,
            normalizer: 'spatie-gd',
            normalizerVersion: 'spatie-gd-v1',
            requestedBy: null,
            requesterName: 'Operador sintético',
        );
    }

    private function generator(): FacialPhotoDerivativeGenerator
    {
        return app(
            FacialPhotoDerivativeGenerator::class
        );
    }

    private function assertGenerationFailure(
        string $expectedFailureCode,
        callable $callback
    ): void {
        try {
            $callback();

            $this->fail(
                'Era esperada uma falha de geração.'
            );
        } catch (
            GenerateFacialPhotoDerivativeException $exception
        ) {
            $this->assertSame(
                $expectedFailureCode,
                $exception->failureCode
            );
        }
    }
}
