<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeException;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeResult;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoDerivativeRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Actions\ReprocessVisitorFacialPhotoDerivativeAction;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Actions\VisitorFacialPhotoDerivativeReprocessingAudit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

final class ReprocessVisitorFacialPhotoDerivativeActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'facial_photos.normalization.enabled',
            true
        );

        config()->set(
            'facial_photos.normalization.async_generation.enabled',
            true
        );

        config()->set(
            'facial_photos.normalization.default_profile',
            'vanguard_normalized'
        );

        config()->set(
            'facial_photos.normalization.policy_version',
            'vanguard-normalization-v1'
        );
    }

    public function test_it_is_visible_only_for_eligible_states(): void
    {
        $this->assertFalse(
            ReprocessVisitorFacialPhotoDerivativeAction::isEligibleRecord(
                $this->visitorWithoutPhoto()
            )
        );

        $this->assertFalse(
            ReprocessVisitorFacialPhotoDerivativeAction::isEligibleRecord(
                $this->visitorWithPhoto(
                    FacialPhotoStatus::PendingValidation
                )
            )
        );

        $this->assertTrue(
            ReprocessVisitorFacialPhotoDerivativeAction::isEligibleRecord(
                $this->visitorWithPhoto(
                    FacialPhotoStatus::Approved
                )
            )
        );

        $this->assertTrue(
            ReprocessVisitorFacialPhotoDerivativeAction::isEligibleRecord(
                $this->visitorWithPhoto(
                    FacialPhotoStatus::Approved,
                    FacialPhotoDerivativeStatus::Failed,
                )
            )
        );

        $this->assertTrue(
            ReprocessVisitorFacialPhotoDerivativeAction::isEligibleRecord(
                $this->visitorWithPhoto(
                    FacialPhotoStatus::Approved,
                    FacialPhotoDerivativeStatus::Pending,
                )
            )
        );

        foreach (
            [
                FacialPhotoDerivativeStatus::Processing,
                FacialPhotoDerivativeStatus::Ready,
                FacialPhotoDerivativeStatus::Superseded,
            ] as $blockedStatus
        ) {
            $this->assertFalse(
                ReprocessVisitorFacialPhotoDerivativeAction::isEligibleRecord(
                    $this->visitorWithPhoto(
                        FacialPhotoStatus::Approved,
                        $blockedStatus,
                    )
                )
            );
        }
    }

    public function test_it_remains_hidden_when_the_feature_is_disabled(): void
    {
        config()->set(
            'facial_photos.normalization.enabled',
            false
        );

        $this->assertFalse(
            ReprocessVisitorFacialPhotoDerivativeAction::isEligibleRecord(
                $this->visitorWithPhoto(
                    FacialPhotoStatus::Approved,
                    FacialPhotoDerivativeStatus::Failed,
                )
            )
        );
    }

    public function test_success_is_recorded_in_the_visitor_history_without_internal_ids(): void
    {
        [
            $visitor,
            $user,
        ] = $this->persistedVisitorAndUser();

        $requestId = (string) Str::uuid();
        $photoId = (string) Str::uuid();

        VisitorFacialPhotoDerivativeReprocessingAudit::success(
            visitor: $visitor,
            user: $user,
            result: new ReprocessFacialPhotoDerivativeResult(
                requestId: $requestId,
                photoId: $photoId,
                previousStatus: FacialPhotoDerivativeStatus::Failed,
                scheduled: true,
            ),
        );

        $activity = Activity::query()
            ->where(
                'subject_type',
                VisitorRecord::class
            )
            ->where(
                'subject_id',
                $visitor->getKey()
            )
            ->where(
                'event',
                'visitor_facial_photo_derivative_reprocessing_requested'
            )
            ->firstOrFail();

        $this->assertSame(
            (string) $user->getKey(),
            (string) $activity->causer_id
        );

        $this->assertSame(
            'Reprocessamento da preparação facial solicitado',
            $activity->description
        );

        $this->assertSame(
            'success',
            $activity->properties->get('status')
        );

        $this->assertTrue(
            $activity->properties->get('scheduled')
        );

        $this->assertSame(
            'failed',
            $activity->properties->get(
                'previous_status'
            )
        );

        $serialized =
            $activity->properties->toJson();

        $this->assertStringNotContainsString(
            $requestId,
            $serialized
        );

        $this->assertStringNotContainsString(
            $photoId,
            $serialized
        );
    }

    public function test_failure_is_recorded_with_a_safe_message(): void
    {
        [
            $visitor,
            $user,
        ] = $this->persistedVisitorAndUser();

        $exception =
            ReprocessFacialPhotoDerivativeException::alreadyReady();

        VisitorFacialPhotoDerivativeReprocessingAudit::failure(
            visitor: $visitor,
            user: $user,
            exception: $exception,
        );

        $activity = Activity::query()
            ->where(
                'subject_type',
                VisitorRecord::class
            )
            ->where(
                'subject_id',
                $visitor->getKey()
            )
            ->where(
                'event',
                'visitor_facial_photo_derivative_reprocessing_failed'
            )
            ->firstOrFail();

        $this->assertSame(
            'failed',
            $activity->properties->get('status')
        );

        $this->assertSame(
            'facial_derivative_already_ready',
            $activity->properties->get(
                'failure_code'
            )
        );

        $this->assertSame(
            $exception->getMessage(),
            $activity->properties->get('message')
        );
    }

    public function test_the_action_is_integrated_with_confirmation_authorization_and_portuguese_notifications(): void
    {
        $action = file_get_contents(
            base_path(
                'app/Modules/Operations/UI/Filament/'
                .'Resources/VisitorRecords/Actions/'
                .'ReprocessVisitorFacialPhotoDerivativeAction.php'
            )
        );

        $table = file_get_contents(
            base_path(
                'app/Modules/Operations/UI/Filament/'
                .'Resources/VisitorRecords/Tables/'
                .'VisitorRecordsTable.php'
            )
        );

        $this->assertIsString(
            $action
        );

        $this->assertIsString(
            $table
        );

        $this->assertStringContainsString(
            "'Reprocessar preparação'",
            $action
        );

        $this->assertStringContainsString(
            '->requiresConfirmation()',
            $action
        );

        $this->assertStringContainsString(
            'Gate::authorize(',
            $action
        );

        $this->assertStringContainsString(
            "'reprocessFacialPhotoDerivative'",
            $action
        );

        $this->assertStringContainsString(
            'ReprocessFacialPhotoDerivativeUseCase::class',
            $action
        );

        $this->assertStringContainsString(
            "'Reprocessamento solicitado'",
            $action
        );

        $this->assertStringContainsString(
            "'Não foi possível reprocessar a preparação'",
            $action
        );

        $this->assertStringContainsString(
            'VisitorFacialPhotoDerivativeReprocessingAudit::success',
            $action
        );

        $this->assertStringContainsString(
            'VisitorFacialPhotoDerivativeReprocessingAudit::failure',
            $action
        );

        $this->assertStringContainsString(
            'ReprocessVisitorFacialPhotoDerivativeAction::make()',
            $table
        );

        $this->assertStringContainsString(
            'VanguardActivityLogTimelineAction::make()',
            $table
        );

        $this->assertStringNotContainsString(
            'source_sha256',
            file_get_contents(
                base_path(
                    'app/Modules/Operations/UI/Filament/'
                    .'Resources/VisitorRecords/Actions/'
                    .'VisitorFacialPhotoDerivativeReprocessingAudit.php'
                )
            )
        );
    }

    private function visitorWithoutPhoto(): VisitorRecord
    {
        $visitor = new VisitorRecord;

        $visitor->setRelation(
            'latestFacialPhoto',
            null
        );

        return $visitor;
    }

    private function visitorWithPhoto(
        FacialPhotoStatus $photoStatus,
        ?FacialPhotoDerivativeStatus $derivativeStatus = null,
    ): VisitorRecord {
        $sourceSha256 = str_repeat(
            'a',
            64
        );

        $photo = new FacialPhotoRecord([
            'id' => (string) Str::uuid(),
            'status' => $photoStatus,
            'sha256' => $sourceSha256,
        ]);

        $derivatives = [];

        if (
            $derivativeStatus
                instanceof FacialPhotoDerivativeStatus
        ) {
            $derivative =
                new FacialPhotoDerivativeRecord([
                    'id' => (string) Str::uuid(),
                    'profile' => 'vanguard_normalized',
                    'policy_version' => 'vanguard-normalization-v1',
                    'status' => $derivativeStatus,
                    'source_sha256' => $sourceSha256,
                ]);

            $derivative->created_at =
                now();

            $derivatives[] =
                $derivative;
        }

        $photo->setRelation(
            'derivatives',
            new Collection(
                $derivatives
            )
        );

        $visitor = new VisitorRecord;

        $visitor->setRelation(
            'latestFacialPhoto',
            $photo
        );

        return $visitor;
    }

    /**
     * @return array{
     *     0: VisitorRecord,
     *     1: User
     * }
     */
    private function persistedVisitorAndUser(): array
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO A5F.4',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE A5F.4 LTDA',
                'display_name' => 'UNIDADE A5F.4',
                'unit_code' => 'A5F-04',
            ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE SINTÉTICO A5F.4',
            'status' => VisitorStatus::Active,
        ]);

        $user = User::factory()->create([
            'name' => 'OPERADOR SINTÉTICO A5F.4',
        ]);

        return [
            $visitor,
            $user,
        ];
    }
}
