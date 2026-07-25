<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationException;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\FacialPhotoValidationPersistenceData;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\FacialPhotoValidationTarget;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatusTransitionPolicy;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationResult;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentExecuteFacialPhotoValidationRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoValidationAttemptRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class EloquentExecuteFacialPhotoValidationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    private EloquentExecuteFacialPhotoValidationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('facial_photos');

        $this->directory = storage_path(
            'framework/testing/facial-photo-execution'
        );

        File::deleteDirectory(
            $this->directory
        );

        File::ensureDirectoryExists(
            $this->directory
        );

        $this->repository =
            new EloquentExecuteFacialPhotoValidationRepository;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(
            $this->directory
        );

        parent::tearDown();
    }

    public function test_it_prepares_the_immutable_original_media(): void
    {
        $context = $this->createContext();

        $target = $this->repository->findTarget(
            $context['photo']->id
        );

        $this->assertInstanceOf(
            FacialPhotoValidationTarget::class,
            $target
        );

        $this->assertSame(
            $context['photo']->id,
            $target->photoId
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $target->status
        );

        $this->assertSame(
            $context['media_id'],
            $target->mediaId
        );

        $this->assertSame(
            $context['media_path'],
            $target->absolutePath
        );

        $this->assertSame(
            $context['sha256'],
            $target->sha256
        );

        $this->assertTrue(
            is_readable(
                $target->absolutePath
            )
        );
    }

    public function test_it_persists_an_approval_atomically_and_preserves_technical_analysis(): void
    {
        $context = $this->createContext();

        $target = $this->target(
            $context
        );

        $technicalVersion =
            $context['photo']->validation_version;

        $technicalResult =
            $context['photo']->validation_result;

        $technicalReasons =
            $context['photo']->rejection_reasons;

        $validation = $this->approvedValidation();

        $result = $this->repository->persist(
            $this->persistenceData(
                target: $target,
                validation: $validation,
                operatorUserId: $context['operator']->id,
            )
        );

        $photo = $context['photo']->refresh();

        $attempt =
            FacialPhotoValidationAttemptRecord::query()
                ->findOrFail(
                    $result->attemptId
                );

        $this->assertSame(
            1,
            $result->attemptNumber
        );

        $this->assertTrue(
            $result->isApproved()
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
            $technicalVersion,
            $photo->validation_version
        );

        $this->assertSame(
            $technicalResult,
            $photo->validation_result
        );

        $this->assertSame(
            $technicalReasons,
            $photo->rejection_reasons
        );

        $this->assertSame(
            $context['operator']->id,
            $attempt->operator_user_id
        );

        $this->assertSame(
            'OPERADOR FACIAL C5',
            $attempt->operator_name
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
            [
                'confidence' => 0.99,
            ],
            $attempt->metrics
        );

        $this->assertSame(
            [],
            $attempt->issues
        );
    }

    public function test_it_numbers_an_inconclusive_attempt_before_a_later_approval(): void
    {
        $context = $this->createContext();

        $firstTarget = $this->target(
            $context
        );

        $inconclusive =
            $this->inconclusiveValidation();

        $first = $this->repository->persist(
            $this->persistenceData(
                target: $firstTarget,
                validation: $inconclusive,
            )
        );

        $this->assertSame(
            1,
            $first->attemptNumber
        );

        $this->assertTrue(
            $first->isInconclusive()
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $context['photo']
                ->refresh()
                ->status
        );

        $secondTarget =
            $this->repository->findTarget(
                $context['photo']->id
            );

        $this->assertInstanceOf(
            FacialPhotoValidationTarget::class,
            $secondTarget
        );

        $approved = $this->approvedValidation();

        $second = $this->repository->persist(
            $this->persistenceData(
                target: $secondTarget,
                validation: $approved,
            )
        );

        $this->assertSame(
            2,
            $second->attemptNumber
        );

        $this->assertSame(
            2,
            $context['photo']
                ->validationAttempts()
                ->count()
        );

        $this->assertSame(
            $second->attemptId,
            $context['photo']
                ->latestValidationAttempt()
                ->first()
                ?->id
        );

        $this->assertSame(
            FacialPhotoStatus::Approved,
            $context['photo']
                ->refresh()
                ->status
        );
    }

    public function test_it_rejects_a_status_changed_after_preparation(): void
    {
        $context = $this->createContext();

        $target = $this->target(
            $context
        );

        $context['photo']
            ->forceFill([
                'status' => FacialPhotoStatus::Approved,
                'approved_at' => now(),
            ])
            ->save();

        $this->assertExecutionException(
            fn () => $this->repository->persist(
                $this->persistenceData(
                    target: $target,
                    validation: $this->approvedValidation(),
                )
            ),
            'A foto facial com situação “Aprovada” não pode ser validada novamente.'
        );

        $this->assertSame(
            0,
            FacialPhotoValidationAttemptRecord::query()
                ->count()
        );
    }

    public function test_it_rejects_media_changed_after_preparation(): void
    {
        $context = $this->createContext();

        $target = $this->target(
            $context
        );

        file_put_contents(
            $target->absolutePath,
            'synthetic-media-change'
        );

        $this->assertExecutionException(
            fn () => $this->repository->persist(
                $this->persistenceData(
                    target: $target,
                    validation: $this->approvedValidation(),
                )
            ),
            'O arquivo original da foto facial foi alterado durante a validação. Repita a operação.'
        );

        $this->assertSame(
            0,
            FacialPhotoValidationAttemptRecord::query()
                ->count()
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $context['photo']
                ->refresh()
                ->status
        );
    }

    public function test_it_rejects_unavailable_media_during_preparation(): void
    {
        $context = $this->createContext();

        unlink(
            $context['media_path']
        );

        $this->assertExecutionException(
            fn () => $this->repository->findTarget(
                $context['photo']->id
            ),
            'O arquivo original da foto facial não está disponível para validação.'
        );
    }

    public function test_it_rejects_an_unknown_operator_without_creating_the_ledger(): void
    {
        $context = $this->createContext();

        $target = $this->target(
            $context
        );

        $this->assertExecutionException(
            fn () => $this->repository->persist(
                $this->persistenceData(
                    target: $target,
                    validation: $this->approvedValidation(),
                    operatorUserId: 999_999,
                )
            ),
            'O operador responsável pela validação facial não foi encontrado.'
        );

        $this->assertSame(
            0,
            FacialPhotoValidationAttemptRecord::query()
                ->count()
        );
    }

    public function test_it_rejects_attempts_after_the_smallint_limit(): void
    {
        $context = $this->createContext();

        FacialPhotoValidationAttemptRecord::query()
            ->create([
                'facial_photo_id' => $context['photo']->id,
                'tenant_id' => $context['tenant']->id,
                'organization_id' => $context['organization']->id,
                'operator_user_id' => null,
                'operator_name' => null,
                'attempt_number' => 65_535,
                'validator' => 'synthetic-validator',
                'validator_version' => 'synthetic-v1',
                'decision' => FacialPhotoValidationDecision::Inconclusive,
                'face_count' => 0,
                'metrics' => [
                    'available' => false,
                ],
                'issues' => [
                    FacialPhotoValidationIssue::ValidatorUnavailable
                        ->value,
                ],
                'status_before' => FacialPhotoStatus::PendingValidation,
                'status_after' => FacialPhotoStatus::PendingValidation,
                'validated_at' => now(),
            ]);

        $target = $this->target(
            $context
        );

        $this->assertExecutionException(
            fn () => $this->repository->persist(
                $this->persistenceData(
                    target: $target,
                    validation: $this->inconclusiveValidation(),
                )
            ),
            'O limite de tentativas de validação desta foto facial foi atingido.'
        );

        $this->assertSame(
            1,
            FacialPhotoValidationAttemptRecord::query()
                ->count()
        );
    }

    public function test_it_rolls_back_the_ledger_when_the_photo_update_fails(): void
    {
        $context = $this->createContext();

        $target = $this->target(
            $context
        );

        FacialPhotoRecord::saving(
            static function (
                FacialPhotoRecord $photo
            ): void {
                if ($photo->isDirty('status')) {
                    throw new RuntimeException(
                        'falha sintética ao atualizar a foto'
                    );
                }
            }
        );

        try {
            $this->repository->persist(
                $this->persistenceData(
                    target: $target,
                    validation: $this->approvedValidation(),
                )
            );

            $this->fail(
                'A falha da foto deveria desfazer o ledger.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'falha sintética ao atualizar a foto',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            0,
            FacialPhotoValidationAttemptRecord::query()
                ->count()
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $context['photo']
                ->refresh()
                ->status
        );
    }

    /**
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     visitor: VisitorRecord,
     *     operator: User,
     *     photo: FacialPhotoRecord,
     *     media_id: int,
     *     media_path: string,
     *     sha256: string
     * }
     */
    private function createContext(): array
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO FACIAL C5',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE FACIAL C5 LTDA',
                'display_name' => 'UNIDADE FACIAL C5',
                'unit_code' => 'FC5-01',
            ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE FACIAL C5',
            'status' => VisitorStatus::Active,
        ]);

        $operator = User::factory()->create([
            'name' => 'OPERADOR FACIAL C5',
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
                    'captured_at' => '2026-07-25 17:00:00',
                ]);

        $sourcePath = $this->createJpeg(
            'facial-c5.jpg'
        );

        $media = $photo
            ->copyMedia($sourcePath)
            ->usingFileName(
                'facial-c5.jpg'
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

        $photo
            ->forceFill([
                'analyzed_at' => '2026-07-25 17:01:00',
                'width' => 32,
                'height' => 32,
                'mime_type' => 'image/jpeg',
                'size_bytes' => filesize($mediaPath),
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
            'media_id' => (int) $media->id,
            'media_path' => $mediaPath,
            'sha256' => $sha256,
        ];
    }

    /**
     * @param  array{
     *     photo: FacialPhotoRecord
     * }  $context
     */
    private function target(
        array $context
    ): FacialPhotoValidationTarget {
        $target = $this->repository->findTarget(
            $context['photo']->id
        );

        if (
            ! $target
            instanceof FacialPhotoValidationTarget
        ) {
            throw new RuntimeException(
                'O alvo sintético não foi preparado.'
            );
        }

        return $target;
    }

    private function persistenceData(
        FacialPhotoValidationTarget $target,
        FacialPhotoValidationResult $validation,
        ?int $operatorUserId = null,
    ): FacialPhotoValidationPersistenceData {
        $transition =
            (new FacialPhotoStatusTransitionPolicy)
                ->transition(
                    currentStatus: $target->status,
                    decision: $validation->decision,
                );

        return new FacialPhotoValidationPersistenceData(
            target: $target,
            validation: $validation,
            transition: $transition,
            operatorUserId: $operatorUserId,
        );
    }

    private function approvedValidation(): FacialPhotoValidationResult
    {
        return new FacialPhotoValidationResult(
            validator: 'synthetic-validator',
            version: 'synthetic-v1',
            decision: FacialPhotoValidationDecision::Approved,
            faceCount: 1,
            metrics: [
                'confidence' => 0.99,
            ],
            issues: [],
        );
    }

    private function inconclusiveValidation(): FacialPhotoValidationResult
    {
        return new FacialPhotoValidationResult(
            validator: 'synthetic-validator',
            version: 'synthetic-v1',
            decision: FacialPhotoValidationDecision::Inconclusive,
            faceCount: 0,
            metrics: [
                'available' => false,
            ],
            issues: [
                FacialPhotoValidationIssue::ValidatorUnavailable,
            ],
        );
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

    /**
     * @param  callable(): mixed  $callback
     */
    private function assertExecutionException(
        callable $callback,
        string $expectedMessage,
    ): void {
        try {
            $callback();

            $this->fail(
                'Era esperada uma exceção segura da validação facial.'
            );
        } catch (
            ExecuteFacialPhotoValidationException $exception
        ) {
            $this->assertSame(
                $expectedMessage,
                $exception->getMessage()
            );
        }
    }
}
