<?php

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Validation\Execute;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationCommand;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationRepository;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationUseCase;
use App\Modules\Operations\Application\FacialPhotos\Validation\ValidateFacialPhotoUseCase;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatusTransitionPolicy;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Images\Simulator\SimulatedFacialPhotoValidationScenario;
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

final class ExecuteFacialPhotoValidationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('facial_photos');

        $this->directory = storage_path(
            'framework/testing/facial-photo-validation-integration'
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

    public function test_it_approves_a_photo_through_the_complete_transactional_flow(): void
    {
        $context = $this->createContext(
            'approved-flow.jpg'
        );

        $result = $this
            ->useCase(
                SimulatedFacialPhotoValidationScenario::Approved
            )
            ->execute(
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
            'OPERADOR INTEGRAÇÃO FACIAL',
            $attempt->operator_name
        );
    }

    public function test_it_rejects_a_photo_through_an_explicit_simulated_scenario(): void
    {
        $context = $this->createContext(
            'rejected-flow.jpg'
        );

        $result = $this
            ->useCase(
                SimulatedFacialPhotoValidationScenario::NoFaceDetected
            )
            ->execute(
                new ExecuteFacialPhotoValidationCommand(
                    photoId: $context['photo']->id,
                )
            );

        $photo = $context['photo']->refresh();

        $attempt =
            FacialPhotoValidationAttemptRecord::query()
                ->findOrFail(
                    $result->attemptId
                );

        $this->assertTrue(
            $result->isRejected()
        );

        $this->assertSame(
            FacialPhotoStatus::Rejected,
            $photo->status
        );

        $this->assertNull(
            $photo->approved_at
        );

        $this->assertNotNull(
            $photo->rejected_at
        );

        $this->assertSame(
            FacialPhotoValidationDecision::Rejected,
            $attempt->decision
        );

        $this->assertSame(
            0,
            $attempt->face_count
        );

        $this->assertSame(
            [
                FacialPhotoValidationIssue::NoFaceDetected->value,
            ],
            $attempt->issues
        );

        $this->assertSame(
            'no_face_detected',
            $attempt->metrics['scenario']
        );

        $this->assertNull(
            $attempt->operator_user_id
        );

        $this->assertNull(
            $attempt->operator_name
        );
    }

    public function test_it_records_an_inconclusive_attempt_and_allows_a_later_approval(): void
    {
        $context = $this->createContext(
            'retry-flow.jpg'
        );

        $first = $this
            ->useCase(
                SimulatedFacialPhotoValidationScenario::ValidatorUnavailable
            )
            ->execute(
                new ExecuteFacialPhotoValidationCommand(
                    photoId: $context['photo']->id,
                )
            );

        $this->assertTrue(
            $first->isInconclusive()
        );

        $this->assertSame(
            1,
            $first->attemptNumber
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $context['photo']
                ->refresh()
                ->status
        );

        $second = $this
            ->useCase(
                SimulatedFacialPhotoValidationScenario::Approved
            )
            ->execute(
                new ExecuteFacialPhotoValidationCommand(
                    photoId: $context['photo']->id,
                )
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
            $context['photo']
                ->refresh()
                ->status
        );

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

        $this->assertCount(
            2,
            $attempts
        );

        $this->assertSame(
            FacialPhotoValidationDecision::Inconclusive,
            $attempts[0]->decision
        );

        $this->assertSame(
            [
                FacialPhotoValidationIssue::ValidatorUnavailable
                    ->value,
            ],
            $attempts[0]->issues
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $attempts[0]->status_after
        );

        $this->assertSame(
            FacialPhotoValidationDecision::Approved,
            $attempts[1]->decision
        );

        $this->assertSame(
            FacialPhotoStatus::Approved,
            $attempts[1]->status_after
        );
    }

    private function useCase(
        SimulatedFacialPhotoValidationScenario $scenario
    ): ExecuteFacialPhotoValidationUseCase {
        return new ExecuteFacialPhotoValidationUseCase(
            repository: app(
                ExecuteFacialPhotoValidationRepository::class
            ),
            validateFacialPhoto: new ValidateFacialPhotoUseCase(
                new SimulatedFacialPhotoValidator(
                    $scenario
                )
            ),
            transitionPolicy: new FacialPhotoStatusTransitionPolicy,
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
            'name' => 'GRUPO INTEGRAÇÃO FACIAL',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE INTEGRAÇÃO FACIAL LTDA',
                'display_name' => 'UNIDADE INTEGRAÇÃO FACIAL',
                'unit_code' => 'IFC-01',
            ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE INTEGRAÇÃO FACIAL',
            'status' => VisitorStatus::Active,
        ]);

        $operator = User::factory()->create([
            'name' => 'OPERADOR INTEGRAÇÃO FACIAL',
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
                    'captured_at' => '2026-07-25 18:00:00',
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
                'analyzed_at' => '2026-07-25 18:01:00',
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
