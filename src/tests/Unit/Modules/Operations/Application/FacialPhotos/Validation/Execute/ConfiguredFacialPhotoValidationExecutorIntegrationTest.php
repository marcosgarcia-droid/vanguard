<?php

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Validation\Execute;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationCommand;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationException;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\FacialPhotoValidationExecutor;
use App\Modules\Operations\Application\FacialPhotos\Validation\FacialPhotoValidator;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Images\Simulator\SimulatedFacialPhotoValidator;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoValidationAttemptRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class ConfiguredFacialPhotoValidationExecutorIntegrationTest extends TestCase
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
            'framework/testing/configured-facial-validation-executor'
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

    public function test_it_blocks_the_complete_flow_when_validation_is_disabled(): void
    {
        $context = $this->createContext(
            'disabled-executor.jpg'
        );

        $this->configureValidation(
            enabled: false,
            scenario: 'approved',
        );

        $executor = app(
            FacialPhotoValidationExecutor::class
        );

        try {
            $executor->execute(
                new ExecuteFacialPhotoValidationCommand(
                    photoId: $context['photo']->id,
                    operatorUserId: $context['operator']->id,
                )
            );

            $this->fail(
                'A feature flag desativada deveria bloquear o fluxo.'
            );
        } catch (
            ExecuteFacialPhotoValidationException $exception
        ) {
            $this->assertSame(
                'A validação facial está desativada neste ambiente.',
                $exception->getMessage()
            );
        }

        $photo = $context['photo']->refresh();

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $photo->status
        );

        $this->assertNull(
            $photo->approved_at
        );

        $this->assertNull(
            $photo->rejected_at
        );

        $this->assertDatabaseCount(
            'facial_photo_validation_attempts',
            0
        );

        $this->assertFalse(
            app()->bound(
                FacialPhotoValidator::class
            )
        );
    }

    public function test_it_executes_the_bound_executor_and_approves_the_photo(): void
    {
        $context = $this->createContext(
            'approved-executor.jpg'
        );

        $this->configureValidation(
            enabled: true,
            scenario: 'approved',
        );

        $executor = app(
            FacialPhotoValidationExecutor::class
        );

        $result = $executor->execute(
            new ExecuteFacialPhotoValidationCommand(
                photoId: $context['photo']->id,
                operatorUserId: $context['operator']->id,
            )
        );

        $photo = $context['photo']->refresh();

        $attempt =
            FacialPhotoValidationAttemptRecord::query()
                ->findOrFail(
                    $result->attemptId
                );

        $this->assertTrue(
            $result->isApproved()
        );

        $this->assertSame(
            1,
            $result->attemptNumber
        );

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
            FacialPhotoValidationDecision::Approved,
            $attempt->decision
        );

        $this->assertSame(
            1,
            $attempt->face_count
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
            $context['operator']->id,
            $attempt->operator_user_id
        );

        $this->assertSame(
            'OPERADOR EXECUTOR FACIAL',
            $attempt->operator_name
        );

        $this->assertDatabaseCount(
            'facial_photo_validation_attempts',
            1
        );

        $this->assertFalse(
            app()->bound(
                FacialPhotoValidator::class
            )
        );
    }

    public function test_it_reads_a_new_scenario_on_each_resolution_and_preserves_the_ledger(): void
    {
        $context = $this->createContext(
            'retry-executor.jpg'
        );

        $this->configureValidation(
            enabled: true,
            scenario: 'validator_unavailable',
        );

        $firstExecutor = app(
            FacialPhotoValidationExecutor::class
        );

        $first = $firstExecutor->execute(
            new ExecuteFacialPhotoValidationCommand(
                photoId: $context['photo']->id,
            )
        );

        $firstStatus = $context['photo']
            ->refresh()
            ->status;

        $this->configureValidation(
            enabled: true,
            scenario: 'approved',
        );

        $secondExecutor = app(
            FacialPhotoValidationExecutor::class
        );

        $second = $secondExecutor->execute(
            new ExecuteFacialPhotoValidationCommand(
                photoId: $context['photo']->id,
            )
        );

        $photo = $context['photo']->refresh();

        $attempts =
            FacialPhotoValidationAttemptRecord::query()
                ->where(
                    'facial_photo_id',
                    $context['photo']->id
                )
                ->orderBy(
                    'attempt_number'
                )
                ->get();

        $this->assertTrue(
            $first->isInconclusive()
        );

        $this->assertSame(
            1,
            $first->attemptNumber
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $firstStatus
        );

        $this->assertNotSame(
            $firstExecutor,
            $secondExecutor
        );

        $this->assertTrue(
            $second->isApproved()
        );

        $this->assertSame(
            2,
            $second->attemptNumber
        );

        $this->assertSame(
            FacialPhotoStatus::Approved,
            $photo->status
        );

        $this->assertCount(
            2,
            $attempts
        );

        $this->assertSame(
            FacialPhotoValidationDecision::Inconclusive,
            $attempts[0]->decision
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $attempts[0]->status_after
        );

        $this->assertSame(
            'validator_unavailable',
            $attempts[0]->metrics['scenario']
        );

        $this->assertSame(
            FacialPhotoValidationDecision::Approved,
            $attempts[1]->decision
        );

        $this->assertSame(
            FacialPhotoStatus::Approved,
            $attempts[1]->status_after
        );

        $this->assertSame(
            'approved',
            $attempts[1]->metrics['scenario']
        );

        $this->assertFalse(
            app()->bound(
                FacialPhotoValidator::class
            )
        );
    }

    private function configureValidation(
        bool $enabled,
        string $scenario
    ): void {
        config()->set(
            'facial_photos.validation.enabled',
            $enabled
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
            $scenario
        );
    }

    /**
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     visitor: VisitorRecord,
     *     operator: User,
     *     photo: FacialPhotoRecord
     * }
     */
    private function createContext(
        string $fileName
    ): array {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO EXECUTOR FACIAL',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE EXECUTOR FACIAL LTDA',
                'display_name' => 'UNIDADE EXECUTOR FACIAL',
                'unit_code' => 'EFC-01',
            ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE EXECUTOR FACIAL',
            'status' => VisitorStatus::Active,
        ]);

        $operator = User::factory()->create([
            'name' => 'OPERADOR EXECUTOR FACIAL',
        ]);

        $photo =
            $visitor
                ->facialPhotos()
                ->create([
                    'tenant_id' => $tenant->id,
                    'organization_id' => $organization->id,
                    'created_by' => $operator->id,
                    'source' => FacialPhotoSource::Webcam,
                    'status' => FacialPhotoStatus::PendingValidation,
                    'captured_at' => '2026-07-26 11:30:00',
                ]);

        $sourcePath = $this->createJpeg(
            $fileName
        );

        $media = $photo
            ->copyMedia(
                $sourcePath
            )
            ->usingFileName(
                $fileName
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
                'Não foi possível calcular o hash da mídia sintética.'
            );
        }

        $photo
            ->forceFill([
                'analyzed_at' => '2026-07-26 11:31:00',
                'width' => 32,
                'height' => 32,
                'mime_type' => 'image/jpeg',
                'size_bytes' => filesize(
                    $mediaPath
                ),
                'sha256' => $sha256,
                'validation_version' => 'technical-v1',
                'validation_result' => [
                    'version' => 'technical-v1',
                    'passed' => true,
                    'metrics' => [
                        'width' => 32,
                        'height' => 32,
                    ],
                ],
                'rejection_reasons' => [],
            ])
            ->save();

        return [
            'tenant' => $tenant,
            'organization' => $organization,
            'visitor' => $visitor,
            'operator' => $operator,
            'photo' => $photo,
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
}
