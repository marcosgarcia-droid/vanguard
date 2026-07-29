<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoCommand;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoException;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoUseCase;
use App\Modules\Operations\Application\FacialPhotos\TechnicalAnalysis\FacialPhotoTechnicalAnalyzer;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalAnalysis;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentRegisterVisitorFacialPhotoRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use DateTimeImmutable;
use GdImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

final class EloquentRegisterVisitorFacialPhotoRepositoryTest extends TestCase
{
    private const CONFIRMATION_KEY =
        'cccccccccccccccccccccccccccccccc'
        .'cccccccccccccccccccccccccccccccc';

    private const CONFIRMATION_CONTEXT =
        'visitor.test.photo_capture';

    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('facial_photos');

        $this->directory = storage_path(
            'framework/testing/facial-photo-registration'
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

    public function test_it_persists_a_technically_valid_original_as_pending_validation(): void
    {
        [$visitor, $user] =
            $this->createVisitorContext();

        $sourcePath =
            $this->createCheckerboardJpeg(
                'valid.jpg',
                720,
                900
            );

        $capturedAt = new DateTimeImmutable(
            '2026-07-24 15:30:00'
        );

        $result = app(
            RegisterVisitorFacialPhotoUseCase::class
        )->execute(
            new RegisterVisitorFacialPhotoCommand(
                visitorId: $visitor->id,
                absolutePath: $sourcePath,
                expectedSha256: $this->fingerprintFor(
                    $sourcePath
                ),
                originalFileName: '../visitor-original.jpg',
                source: FacialPhotoSource::Webcam,
                createdBy: $user->id,
                capturedAt: $capturedAt,
                confirmationKey: self::CONFIRMATION_KEY,
                confirmationContext: self::CONFIRMATION_CONTEXT,
            )
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $result->status
        );

        $this->assertTrue(
            $result->awaitsAdditionalValidation()
        );

        $this->assertNotSame(
            FacialPhotoStatus::Approved,
            $result->status
        );

        $photo = FacialPhotoRecord::query()
            ->findOrFail($result->photoId);

        $this->assertSame(
            $visitor->tenant_id,
            $photo->tenant_id
        );

        $this->assertSame(
            $visitor->organization_id,
            $photo->organization_id
        );

        $this->assertSame(
            VisitorRecord::class,
            $photo->subject_type
        );

        $this->assertSame(
            $visitor->id,
            $photo->subject_id
        );

        $this->assertSame(
            $user->id,
            $photo->created_by
        );

        $this->assertSame(
            FacialPhotoSource::Webcam,
            $photo->source
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $photo->status
        );

        $this->assertSame(
            720,
            $photo->width
        );

        $this->assertSame(
            900,
            $photo->height
        );

        $this->assertSame(
            'image/jpeg',
            $photo->mime_type
        );

        $this->assertNotNull(
            $photo->size_bytes
        );

        $this->assertSame(
            $this->fingerprintFor(
                $sourcePath
            ),
            $photo->sha256
        );

        $this->assertSame(
            'technical-v1',
            $photo->validation_version
        );

        $this->assertTrue(
            $photo->validation_result['passed']
        );

        $this->assertSame(
            [],
            $photo->rejection_reasons
        );

        $this->assertNotNull(
            $photo->analyzed_at
        );

        $this->assertNull(
            $photo->approved_at
        );

        $this->assertNull(
            $photo->rejected_at
        );

        $this->assertSame(
            '2026-07-24 15:30:00',
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

        $this->assertSame(
            'visitor-original.jpg',
            $media->file_name
        );

        $this->assertSame(
            'facial_photos',
            $media->disk
        );

        Storage::disk('facial_photos')
            ->assertExists(
                $media->getPathRelativeToRoot()
            );

        $this->assertFileExists(
            $sourcePath
        );

        $visitor->refresh();

        $this->assertNull(
            $visitor->photo_path
        );
    }

    public function test_it_persists_a_technically_rejected_original_for_audit(): void
    {
        [$visitor, $user] =
            $this->createVisitorContext();

        $sourcePath = $this->createSolidJpeg(
            'dark.jpg',
            720,
            900,
            12
        );

        $result = app(
            RegisterVisitorFacialPhotoUseCase::class
        )->execute(
            new RegisterVisitorFacialPhotoCommand(
                visitorId: $visitor->id,
                absolutePath: $sourcePath,
                expectedSha256: $this->fingerprintFor(
                    $sourcePath
                ),
                originalFileName: 'dark.jpg',
                source: FacialPhotoSource::FileUpload,
                createdBy: $user->id,
                confirmationKey: self::CONFIRMATION_KEY,
                confirmationContext: self::CONFIRMATION_CONTEXT,
            )
        );

        $this->assertSame(
            FacialPhotoStatus::Rejected,
            $result->status
        );

        $this->assertTrue(
            $result->isRejected()
        );

        $photo = FacialPhotoRecord::query()
            ->findOrFail($result->photoId);

        $this->assertSame(
            FacialPhotoStatus::Rejected,
            $photo->status
        );

        $this->assertNotNull(
            $photo->rejected_at
        );

        $this->assertContains(
            'underexposed',
            $photo->rejection_reasons
        );

        $this->assertContains(
            'low_contrast',
            $photo->rejection_reasons
        );

        $this->assertContains(
            'low_sharpness',
            $photo->rejection_reasons
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

        $this->assertFileExists(
            $sourcePath
        );
    }

    public function test_it_rolls_back_database_and_private_file_when_analysis_fails(): void
    {
        [$visitor, $user] =
            $this->createVisitorContext();

        $sourcePath =
            $this->createCheckerboardJpeg(
                'rollback.jpg',
                720,
                900
            );

        app()->instance(
            FacialPhotoTechnicalAnalyzer::class,
            new class implements FacialPhotoTechnicalAnalyzer
            {
                public function analyze(
                    string $absolutePath
                ): FacialPhotoTechnicalAnalysis {
                    throw new RuntimeException(
                        'Falha sintética do analisador.'
                    );
                }
            }
        );

        try {
            app(
                RegisterVisitorFacialPhotoUseCase::class
            )->execute(
                new RegisterVisitorFacialPhotoCommand(
                    visitorId: $visitor->id,
                    absolutePath: $sourcePath,
                    expectedSha256: $this->fingerprintFor(
                        $sourcePath
                    ),
                    originalFileName: 'rollback.jpg',
                    source: FacialPhotoSource::Webcam,
                    createdBy: $user->id,
                    confirmationKey: self::CONFIRMATION_KEY,
                    confirmationContext: self::CONFIRMATION_CONTEXT,
                )
            );

            $this->fail(
                'A falha sintética deveria impedir o registro.'
            );
        } catch (
            RegisterVisitorFacialPhotoException $exception
        ) {
            $this->assertSame(
                'Não foi possível registrar e analisar a foto facial.',
                $exception->getMessage()
            );

            $this->assertSame(
                'Falha sintética do analisador.',
                $exception->getPrevious()?->getMessage()
            );
        }

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
            Storage::disk('facial_photos')
                ->allFiles()
        );

        $this->assertFileExists(
            $sourcePath
        );
    }

    public function test_it_rolls_back_when_the_private_media_fingerprint_does_not_match(): void
    {
        [$visitor] =
            $this->createVisitorContext();

        $sourcePath =
            $this->createCheckerboardJpeg(
                'fingerprint-mismatch.jpg',
                720,
                900
            );

        try {
            app(
                RegisterVisitorFacialPhotoUseCase::class
            )->execute(
                new RegisterVisitorFacialPhotoCommand(
                    visitorId: $visitor->id,
                    absolutePath: $sourcePath,
                    originalFileName: 'fingerprint-mismatch.jpg',
                    expectedSha256: str_repeat('f', 64),
                    source: FacialPhotoSource::Webcam,
                    confirmationKey: self::CONFIRMATION_KEY,
                    confirmationContext: self::CONFIRMATION_CONTEXT,
                )
            );

            $this->fail(
                'A divergência do SHA-256 deveria impedir o registro.'
            );
        } catch (
            RegisterVisitorFacialPhotoException $exception
        ) {
            $this->assertSame(
                'A foto facial armazenada não corresponde à imagem confirmada. '
                    .'Capture ou selecione a foto novamente.',
                $exception->getMessage()
            );
        }

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
            Storage::disk('facial_photos')
                ->allFiles()
        );

        $this->assertFileExists(
            $sourcePath
        );
    }

    public function test_it_rejects_a_missing_visitor_without_creating_media(): void
    {
        $sourcePath =
            $this->createCheckerboardJpeg(
                'missing-visitor.jpg',
                720,
                900
            );

        try {
            app(
                RegisterVisitorFacialPhotoUseCase::class
            )->execute(
                new RegisterVisitorFacialPhotoCommand(
                    visitorId: (string) Str::uuid(),
                    absolutePath: $sourcePath,
                    expectedSha256: $this->fingerprintFor(
                        $sourcePath
                    ),
                    originalFileName: 'missing-visitor.jpg',
                    source: FacialPhotoSource::Webcam,
                    confirmationKey: self::CONFIRMATION_KEY,
                    confirmationContext: self::CONFIRMATION_CONTEXT,
                )
            );

            $this->fail(
                'O visitante inexistente deveria ser rejeitado.'
            );
        } catch (
            RegisterVisitorFacialPhotoException $exception
        ) {
            $this->assertSame(
                'O visitante informado para a foto facial não foi encontrado.',
                $exception->getMessage()
            );
        }

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
            Storage::disk('facial_photos')
                ->allFiles()
        );

        $this->assertFileExists(
            $sourcePath
        );
    }

    public function test_it_rejects_an_unavailable_source_before_opening_a_transaction(): void
    {
        [$visitor] =
            $this->createVisitorContext();

        try {
            app(
                RegisterVisitorFacialPhotoUseCase::class
            )->execute(
                new RegisterVisitorFacialPhotoCommand(
                    visitorId: $visitor->id,
                    absolutePath: $this->directory
                            .'/missing.jpg',
                    originalFileName: 'missing.jpg',
                    expectedSha256: str_repeat('a', 64),
                    source: FacialPhotoSource::FileUpload,
                    confirmationKey: self::CONFIRMATION_KEY,
                    confirmationContext: self::CONFIRMATION_CONTEXT,
                )
            );

            $this->fail(
                'O arquivo inexistente deveria ser rejeitado.'
            );
        } catch (
            RegisterVisitorFacialPhotoException $exception
        ) {
            $this->assertSame(
                'O arquivo original da foto facial não está disponível.',
                $exception->getMessage()
            );
        }

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
            Storage::disk('facial_photos')
                ->allFiles()
        );
    }

    /**
     * @return array{VisitorRecord, User}
     */
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

    private function fingerprintFor(
        string $absolutePath
    ): string {
        $fingerprint = hash_file(
            'sha256',
            $absolutePath
        );

        $this->assertIsString(
            $fingerprint
        );

        return $fingerprint;
    }

    private function createCheckerboardJpeg(
        string $filename,
        int $width,
        int $height
    ): string {
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

        return $this->saveJpeg(
            $filename,
            $image
        );
    }

    private function createSolidJpeg(
        string $filename,
        int $width,
        int $height,
        int $value
    ): string {
        $image = imagecreatetruecolor(
            $width,
            $height
        );

        $this->assertInstanceOf(
            GdImage::class,
            $image
        );

        $color = imagecolorallocate(
            $image,
            $value,
            $value,
            $value
        );

        imagefill(
            $image,
            0,
            0,
            $color
        );

        return $this->saveJpeg(
            $filename,
            $image
        );
    }

    private function saveJpeg(
        string $filename,
        GdImage $image
    ): string {
        $path = $this->directory
            .'/'
            .$filename;

        $this->assertTrue(
            imagejpeg(
                $image,
                $path,
                92
            )
        );

        imagedestroy($image);

        return $path;
    }

    public function test_it_sanitizes_the_original_file_name_before_persisting_media(): void
    {
        $reflection = new \ReflectionClass(
            EloquentRegisterVisitorFacialPhotoRepository::class
        );

        $repository = $reflection
            ->newInstanceWithoutConstructor();

        $method = $reflection->getMethod(
            'safeFileName'
        );

        $method->setAccessible(
            true
        );

        $command = new RegisterVisitorFacialPhotoCommand(
            visitorId: 'synthetic-visitor',
            absolutePath: '/tmp/synthetic-source.JPG',
            originalFileName: '../../ Foto do Visitante ??.JPG',
            expectedSha256: str_repeat('a', 64),
            source: FacialPhotoSource::FileUpload,
            confirmationKey: self::CONFIRMATION_KEY,
            confirmationContext: self::CONFIRMATION_CONTEXT,
        );

        $this->assertSame(
            'foto-do-visitante.jpg',
            $method->invoke(
                $repository,
                $command
            )
        );
    }
}
