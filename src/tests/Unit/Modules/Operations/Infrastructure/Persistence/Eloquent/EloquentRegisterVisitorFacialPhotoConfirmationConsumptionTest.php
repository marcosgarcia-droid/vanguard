<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoCommand;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoException;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoUseCase;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoConfirmationConsumptionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use GdImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EloquentRegisterVisitorFacialPhotoConfirmationConsumptionTest extends TestCase
{
    use RefreshDatabase;

    private const FIRST_CONFIRMATION_KEY =
        'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
        .'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const SECOND_CONFIRMATION_KEY =
        'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'
        .'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('facial_photos');

        $this->directory = storage_path(
            'framework/testing/facial-photo-confirmation-consumption'
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

    public function test_it_consumes_the_confirmation_in_the_same_registration_transaction(): void
    {
        [$visitor, $user] =
            $this->createVisitorContext();

        $sourcePath =
            $this->createCheckerboardJpeg(
                'confirmed.jpg',
                false
            );

        $context =
            'visitor.update.'
            .$visitor->id
            .'.photo_capture';

        $result = $this->register(
            visitor: $visitor,
            user: $user,
            sourcePath: $sourcePath,
            confirmationKey: self::FIRST_CONFIRMATION_KEY,
            confirmationContext: $context,
        );

        $photo = FacialPhotoRecord::query()
            ->findOrFail(
                $result->photoId
            );

        $consumption =
            FacialPhotoConfirmationConsumptionRecord::query()
                ->sole();

        $this->assertSame(
            $photo->id,
            $consumption->facial_photo_id
        );

        $this->assertSame(
            $visitor->id,
            $consumption->visitor_id
        );

        $this->assertSame(
            $visitor->tenant_id,
            $consumption->tenant_id
        );

        $this->assertSame(
            $visitor->organization_id,
            $consumption->organization_id
        );

        $this->assertSame(
            $user->id,
            $consumption->confirmed_by
        );

        $this->assertSame(
            self::FIRST_CONFIRMATION_KEY,
            $consumption->confirmation_key
        );

        $this->assertSame(
            $context,
            $consumption->confirmation_context
        );

        $this->assertSame(
            $this->fingerprintFor(
                $sourcePath
            ),
            $consumption->photo_sha256
        );

        $this->assertTrue(
            $photo
                ->confirmationConsumption
                ->is($consumption)
        );

        $this->assertTrue(
            $visitor
                ->facialPhotoConfirmationConsumptions()
                ->sole()
                ->is($consumption)
        );
    }

    public function test_reusing_the_same_confirmation_rolls_back_the_second_photo_and_media(): void
    {
        [$visitor, $user] =
            $this->createVisitorContext();

        $firstPath =
            $this->createCheckerboardJpeg(
                'first.jpg',
                false
            );

        $secondPath =
            $this->createCheckerboardJpeg(
                'second.jpg',
                true
            );

        $context =
            'visitor.update.'
            .$visitor->id
            .'.photo_capture';

        $this->register(
            visitor: $visitor,
            user: $user,
            sourcePath: $firstPath,
            confirmationKey: self::FIRST_CONFIRMATION_KEY,
            confirmationContext: $context,
        );

        try {
            $this->register(
                visitor: $visitor,
                user: $user,
                sourcePath: $secondPath,
                confirmationKey: self::FIRST_CONFIRMATION_KEY,
                confirmationContext: $context,
            );

            $this->fail(
                'A confirmação reutilizada deveria ser rejeitada.'
            );
        } catch (
            RegisterVisitorFacialPhotoException $exception
        ) {
            $this->assertSame(
                'Esta confirmação da foto facial já foi utilizada. '
                    .'Analise ou capture a imagem novamente.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount(
            'facial_photos',
            1
        );

        $this->assertDatabaseCount(
            'facial_photo_confirmation_consumptions',
            1
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

        $this->assertFileExists(
            $secondPath
        );
    }

    public function test_a_new_confirmation_allows_a_legitimate_future_photo(): void
    {
        [$visitor, $user] =
            $this->createVisitorContext();

        $firstPath =
            $this->createCheckerboardJpeg(
                'legitimate-first.jpg',
                false
            );

        $secondPath =
            $this->createCheckerboardJpeg(
                'legitimate-second.jpg',
                true
            );

        $context =
            'visitor.update.'
            .$visitor->id
            .'.photo_capture';

        $this->register(
            visitor: $visitor,
            user: $user,
            sourcePath: $firstPath,
            confirmationKey: self::FIRST_CONFIRMATION_KEY,
            confirmationContext: $context,
        );

        $this->register(
            visitor: $visitor,
            user: $user,
            sourcePath: $secondPath,
            confirmationKey: self::SECOND_CONFIRMATION_KEY,
            confirmationContext: $context,
        );

        $this->assertDatabaseCount(
            'facial_photos',
            2
        );

        $this->assertDatabaseCount(
            'facial_photo_confirmation_consumptions',
            2
        );

        $this->assertDatabaseCount(
            'media',
            2
        );

        $this->assertCount(
            2,
            Storage::disk('facial_photos')
                ->allFiles()
        );
    }

    /**
     * @return array{
     *     0: VisitorRecord,
     *     1: User
     * }
     */
    private function createVisitorContext(): array
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO CONSUMO ATÔMICO',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE CONSUMO ATÔMICO LTDA',
                'display_name' => 'UNIDADE CONSUMO ATÔMICO',
                'unit_code' => 'FCA-01',
            ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE CONSUMO ATÔMICO',
            'status' => VisitorStatus::Active,
        ]);

        $user = User::factory()->create([
            'name' => 'OPERADOR CONSUMO ATÔMICO',
        ]);

        return [
            $visitor,
            $user,
        ];
    }

    private function register(
        VisitorRecord $visitor,
        User $user,
        string $sourcePath,
        string $confirmationKey,
        string $confirmationContext
    ): mixed {
        return app(
            RegisterVisitorFacialPhotoUseCase::class
        )->execute(
            new RegisterVisitorFacialPhotoCommand(
                visitorId: $visitor->id,
                absolutePath: $sourcePath,
                originalFileName: basename(
                    $sourcePath
                ),
                expectedSha256: $this->fingerprintFor(
                    $sourcePath
                ),
                source: FacialPhotoSource::Webcam,
                confirmationKey: $confirmationKey,
                confirmationContext: $confirmationContext,
                createdBy: $user->id,
            )
        );
    }

    private function createCheckerboardJpeg(
        string $fileName,
        bool $inverted
    ): string {
        $path =
            $this->directory
            .DIRECTORY_SEPARATOR
            .$fileName;

        $image = imagecreatetruecolor(
            720,
            900
        );

        if (! $image instanceof GdImage) {
            $this->fail(
                'Não foi possível criar a imagem sintética.'
            );
        }

        $dark = imagecolorallocate(
            $image,
            40,
            40,
            40
        );

        $light = imagecolorallocate(
            $image,
            220,
            220,
            220
        );

        for ($y = 0; $y < 900; $y += 40) {
            for ($x = 0; $x < 720; $x += 40) {
                $even =
                    (
                        intdiv($x, 40)
                        + intdiv($y, 40)
                    ) % 2 === 0;

                $useLight = $inverted
                    ? ! $even
                    : $even;

                imagefilledrectangle(
                    $image,
                    $x,
                    $y,
                    min(
                        $x + 39,
                        719
                    ),
                    min(
                        $y + 39,
                        899
                    ),
                    $useLight
                        ? $light
                        : $dark
                );
            }
        }

        imagejpeg(
            $image,
            $path,
            90
        );

        imagedestroy(
            $image
        );

        $this->assertFileExists(
            $path
        );

        return $path;
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
}
