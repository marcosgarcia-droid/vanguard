<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Validation\Execute;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeCommand;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Schedule\FacialPhotoDerivativeAfterCommitScheduler;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationCommand;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationException;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\FacialPhotoValidationExecutor;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Queue\GenerateFacialPhotoDerivativeJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class ConfiguredFacialPhotoValidationDerivativeSchedulingIntegrationTest extends TestCase
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
            'framework/testing/facial-derivative-after-approval'
        );

        File::deleteDirectory(
            $this->directory
        );

        File::ensureDirectoryExists(
            $this->directory
        );

        $this->configureFlow(
            scenario: 'approved',
            generationEnabled: true,
        );
    }

    protected function tearDown(): void
    {
        while (
            DB::connection()->transactionLevel() > 0
        ) {
            DB::rollBack();
        }

        File::deleteDirectory(
            $this->directory
        );

        parent::tearDown();
    }

    public function test_approval_schedules_the_derivative_with_the_approved_operator(): void
    {
        Bus::fake();

        $context = $this->createContext(
            'approved-scheduling.jpg'
        );

        $result = $this->executor()->execute(
            new ExecuteFacialPhotoValidationCommand(
                photoId: $context['photo']->id,
                operatorUserId: $context['operator']->id,
            )
        );

        $this->assertTrue(
            $result->isApproved()
        );

        $this->assertSame(
            FacialPhotoStatus::Approved,
            $context['photo']->refresh()->status
        );

        Bus::assertDispatched(
            GenerateFacialPhotoDerivativeJob::class,
            static fn (
                GenerateFacialPhotoDerivativeJob $job
            ): bool => $job->photoId
                === $context['photo']->id
                && $job->profile
                    === 'vanguard_normalized'
                && $job->policyVersion
                    === 'vanguard-normalization-v1'
                && $job->normalizer
                    === 'spatie-gd'
                && $job->normalizerVersion
                    === 'spatie-gd-v1'
                && $job->requestedBy
                    === $context['operator']->id
                && $job->requesterName === null
                && $job->connection === 'redis'
                && $job->queue === 'default'
        );
    }

    public function test_rejected_validation_does_not_schedule_a_derivative(): void
    {
        Bus::fake();

        $this->configureFlow(
            scenario: 'no_face_detected',
            generationEnabled: true,
        );

        $context = $this->createContext(
            'rejected-scheduling.jpg'
        );

        $result = $this->executor()->execute(
            new ExecuteFacialPhotoValidationCommand(
                photoId: $context['photo']->id,
            )
        );

        $this->assertTrue(
            $result->isRejected()
        );

        Bus::assertNotDispatched(
            GenerateFacialPhotoDerivativeJob::class
        );
    }

    public function test_inconclusive_validation_does_not_schedule_a_derivative(): void
    {
        Bus::fake();

        $this->configureFlow(
            scenario: 'validator_unavailable',
            generationEnabled: true,
        );

        $context = $this->createContext(
            'inconclusive-scheduling.jpg'
        );

        $result = $this->executor()->execute(
            new ExecuteFacialPhotoValidationCommand(
                photoId: $context['photo']->id,
            )
        );

        $this->assertTrue(
            $result->isInconclusive()
        );

        Bus::assertNotDispatched(
            GenerateFacialPhotoDerivativeJob::class
        );
    }

    public function test_disabled_generation_does_not_schedule_after_approval(): void
    {
        Bus::fake();

        $this->configureFlow(
            scenario: 'approved',
            generationEnabled: false,
        );

        $context = $this->createContext(
            'disabled-generation.jpg'
        );

        $result = $this->executor()->execute(
            new ExecuteFacialPhotoValidationCommand(
                photoId: $context['photo']->id,
            )
        );

        $this->assertTrue(
            $result->isApproved()
        );

        Bus::assertNotDispatched(
            GenerateFacialPhotoDerivativeJob::class
        );
    }

    public function test_outer_transaction_dispatches_only_after_commit(): void
    {
        Bus::fake();

        $context = $this->createContext(
            'outer-commit.jpg'
        );

        DB::beginTransaction();

        $result = $this->executor()->execute(
            new ExecuteFacialPhotoValidationCommand(
                photoId: $context['photo']->id,
                operatorUserId: $context['operator']->id,
            )
        );

        $this->assertTrue(
            $result->isApproved()
        );

        Bus::assertNotDispatched(
            GenerateFacialPhotoDerivativeJob::class
        );

        DB::commit();

        Bus::assertDispatchedTimes(
            GenerateFacialPhotoDerivativeJob::class,
            1
        );
    }

    public function test_outer_transaction_rollback_discards_the_dispatch(): void
    {
        Bus::fake();

        $context = $this->createContext(
            'outer-rollback.jpg'
        );

        DB::beginTransaction();

        $result = $this->executor()->execute(
            new ExecuteFacialPhotoValidationCommand(
                photoId: $context['photo']->id,
            )
        );

        $this->assertTrue(
            $result->isApproved()
        );

        Bus::assertNotDispatched(
            GenerateFacialPhotoDerivativeJob::class
        );

        DB::rollBack();

        Bus::assertNotDispatched(
            GenerateFacialPhotoDerivativeJob::class
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $context['photo']->refresh()->status
        );
    }

    public function test_repeated_approval_cannot_duplicate_the_job(): void
    {
        Bus::fake();

        $context = $this->createContext(
            'duplicate-approval.jpg'
        );

        $executor = $this->executor();

        $executor->execute(
            new ExecuteFacialPhotoValidationCommand(
                photoId: $context['photo']->id,
            )
        );

        try {
            $executor->execute(
                new ExecuteFacialPhotoValidationCommand(
                    photoId: $context['photo']->id,
                )
            );

            $this->fail(
                'A foto aprovada não deveria ser validada novamente.'
            );
        } catch (
            ExecuteFacialPhotoValidationException $exception
        ) {
            $this->assertStringContainsString(
                'não pode ser validada novamente',
                $exception->getMessage()
            );
        }

        Bus::assertDispatchedTimes(
            GenerateFacialPhotoDerivativeJob::class,
            1
        );
    }

    public function test_scheduler_failure_does_not_undo_the_approval(): void
    {
        $this->app->bind(
            FacialPhotoDerivativeAfterCommitScheduler::class,
            static fn (): FacialPhotoDerivativeAfterCommitScheduler => new class implements FacialPhotoDerivativeAfterCommitScheduler
            {
                public function schedule(
                    GenerateFacialPhotoDerivativeCommand $command
                ): bool {
                    throw new RuntimeException(
                        'falha sintética no scheduler'
                    );
                }
            }
        );

        $context = $this->createContext(
            'scheduler-failure.jpg'
        );

        $result = $this->executor()->execute(
            new ExecuteFacialPhotoValidationCommand(
                photoId: $context['photo']->id,
                operatorUserId: $context['operator']->id,
            )
        );

        $this->assertTrue(
            $result->isApproved()
        );

        $this->assertSame(
            FacialPhotoStatus::Approved,
            $context['photo']->refresh()->status
        );

        $this->assertNotNull(
            $context['photo']->refresh()->approved_at
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
            'name' => 'GRUPO A5E',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE A5E LTDA',
                'display_name' => 'UNIDADE A5E',
                'unit_code' => 'A5E-01',
            ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE SINTÉTICO A5E',
            'status' => VisitorStatus::Active,
        ]);

        $operator = User::factory()->create([
            'name' => 'OPERADOR SINTÉTICO A5E',
        ]);

        $photo = $visitor
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
            'validation_version' => 'technical-a5e-v1',
            'validation_result' => [
                'version' => 'technical-a5e-v1',
                'passed' => true,
                'metrics' => [
                    'width' => 32,
                    'height' => 32,
                ],
            ],
            'rejection_reasons' => [],
        ])->save();

        return [
            'tenant' => $tenant,
            'organization' => $organization,
            'visitor' => $visitor,
            'operator' => $operator,
            'photo' => $photo->fresh(),
        ];
    }

    private function createJpeg(
        string $fileName
    ): string {
        $path =
            $this->directory
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

    private function configureFlow(
        string $scenario,
        bool $generationEnabled,
    ): void {
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
            $scenario
        );

        config()->set(
            'facial_photos.normalization.enabled',
            true
        );

        config()->set(
            'facial_photos.normalization.async_generation.enabled',
            $generationEnabled
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

    private function executor(): FacialPhotoValidationExecutor
    {
        return app(
            FacialPhotoValidationExecutor::class
        );
    }
}
