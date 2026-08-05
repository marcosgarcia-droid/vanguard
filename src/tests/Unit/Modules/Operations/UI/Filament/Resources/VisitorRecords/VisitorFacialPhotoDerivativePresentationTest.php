<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeAttemptStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoDerivativeAttemptRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoDerivativeRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Schemas\VisitorFacialPhotoDerivativePresentation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class VisitorFacialPhotoDerivativePresentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'facial_photos.normalization.default_profile',
            'vanguard_normalized'
        );

        config()->set(
            'facial_photos.normalization.policy_version',
            'vanguard-normalization-v1'
        );

        config()->set(
            'facial_photos.normalization.enabled',
            false
        );

        config()->set(
            'facial_photos.normalization.async_generation.enabled',
            false
        );
    }

    public function test_it_reports_not_started_without_a_photo(): void
    {
        $visitor = new VisitorRecord;

        $visitor->setRelation(
            'latestFacialPhoto',
            null
        );

        $this->assertSame(
            [
                'label' => 'Não iniciada',
                'color' => 'gray',
            ],
            VisitorFacialPhotoDerivativePresentation::summary(
                $visitor
            )
        );

        $this->assertSame(
            [],
            VisitorFacialPhotoDerivativePresentation::details(
                $visitor
            )
        );
    }

    public function test_it_waits_for_photo_approval(): void
    {
        $visitor = $this->visitor(
            FacialPhotoStatus::PendingValidation
        );

        $this->assertSame(
            [
                'label' => 'Aguardando aprovação da foto',
                'color' => 'warning',
            ],
            VisitorFacialPhotoDerivativePresentation::summary(
                $visitor
            )
        );
    }

    public function test_it_reports_disabled_generation_for_an_approved_photo(): void
    {
        $visitor = $this->visitor(
            FacialPhotoStatus::Approved
        );

        $this->assertSame(
            [
                'label' => 'Preparação desativada',
                'color' => 'gray',
            ],
            VisitorFacialPhotoDerivativePresentation::summary(
                $visitor
            )
        );

        $this->assertSame(
            [
                'A preparação automática está desativada neste ambiente.',
            ],
            VisitorFacialPhotoDerivativePresentation::details(
                $visitor
            )
        );
    }

    public function test_it_reports_waiting_when_generation_is_enabled(): void
    {
        config()->set(
            'facial_photos.normalization.enabled',
            true
        );

        config()->set(
            'facial_photos.normalization.async_generation.enabled',
            true
        );

        $visitor = $this->visitor(
            FacialPhotoStatus::Approved
        );

        $this->assertSame(
            [
                'label' => 'Aguardando preparação',
                'color' => 'warning',
            ],
            VisitorFacialPhotoDerivativePresentation::summary(
                $visitor
            )
        );
    }

    public function test_it_presents_the_latest_processing_attempt(): void
    {
        $attempt = $this->attempt(
            number: 2,
            status: FacialPhotoDerivativeAttemptStatus::Processing,
            startedAt: Carbon::parse(
                '2026-08-05 07:30:00'
            ),
        );

        $derivative = $this->derivative(
            status: FacialPhotoDerivativeStatus::Processing,
            attempt: $attempt,
        );

        $visitor = $this->visitor(
            FacialPhotoStatus::Approved,
            [$derivative],
        );

        $this->assertSame(
            [
                'label' => 'Em preparação',
                'color' => 'info',
            ],
            VisitorFacialPhotoDerivativePresentation::summary(
                $visitor
            )
        );

        $details =
            VisitorFacialPhotoDerivativePresentation::details(
                $visitor
            );

        $this->assertContains(
            'Última tentativa: 2 — Em processamento.',
            $details
        );

        $this->assertContains(
            'Processamento iniciado em 05/08/2026 07:30:00.',
            $details
        );
    }

    public function test_it_presents_a_ready_derivative_without_exposing_hashes(): void
    {
        $attempt = $this->attempt(
            number: 1,
            status: FacialPhotoDerivativeAttemptStatus::Succeeded,
            startedAt: Carbon::parse(
                '2026-08-05 07:30:00'
            ),
            finishedAt: Carbon::parse(
                '2026-08-05 07:30:03'
            ),
        );

        $derivative = $this->derivative(
            status: FacialPhotoDerivativeStatus::Ready,
            attempt: $attempt,
            generatedAt: Carbon::parse(
                '2026-08-05 07:30:03'
            ),
            width: 600,
            height: 800,
            mimeType: 'image/jpeg',
            sizeBytes: 102_400,
        );

        $visitor = $this->visitor(
            FacialPhotoStatus::Approved,
            [$derivative],
        );

        $this->assertSame(
            [
                'label' => 'Preparada',
                'color' => 'success',
            ],
            VisitorFacialPhotoDerivativePresentation::summary(
                $visitor
            )
        );

        $details =
            VisitorFacialPhotoDerivativePresentation::details(
                $visitor
            );

        $this->assertContains(
            'Preparação concluída em 05/08/2026 07:30:03.',
            $details
        );

        $this->assertContains(
            'Imagem preparada: 600 × 800 px — JPEG — 100,0 KB.',
            $details
        );

        $serialized = implode(
            ' ',
            $details
        );

        $this->assertStringNotContainsString(
            'sha256',
            strtolower($serialized)
        );
    }

    public function test_it_translates_a_failure_without_exposing_the_internal_code(): void
    {
        $attempt = $this->attempt(
            number: 3,
            status: FacialPhotoDerivativeAttemptStatus::Failed,
            startedAt: Carbon::parse(
                '2026-08-05 07:30:00'
            ),
            finishedAt: Carbon::parse(
                '2026-08-05 07:30:02'
            ),
            failureCode: 'source_unavailable',
        );

        $derivative = $this->derivative(
            status: FacialPhotoDerivativeStatus::Failed,
            attempt: $attempt,
            failureCode: 'source_unavailable',
        );

        $visitor = $this->visitor(
            FacialPhotoStatus::Approved,
            [$derivative],
        );

        $this->assertSame(
            [
                'label' => 'Falha na preparação',
                'color' => 'danger',
            ],
            VisitorFacialPhotoDerivativePresentation::summary(
                $visitor
            )
        );

        $details =
            VisitorFacialPhotoDerivativePresentation::details(
                $visitor
            );

        $this->assertContains(
            'Motivo: o arquivo original não está disponível. Atualize a foto facial.',
            $details
        );

        $this->assertStringNotContainsString(
            'source_unavailable',
            implode(
                ' ',
                $details
            )
        );
    }

    public function test_it_uses_only_the_configured_profile_and_policy(): void
    {
        $unrelated = $this->derivative(
            status: FacialPhotoDerivativeStatus::Ready,
            profile: 'device_specific',
            policyVersion: 'other-policy',
            createdAt: Carbon::parse(
                '2026-08-05 08:00:00'
            ),
        );

        $configured = $this->derivative(
            status: FacialPhotoDerivativeStatus::Failed,
            failureCode: 'generation_failed',
            createdAt: Carbon::parse(
                '2026-08-05 07:00:00'
            ),
        );

        $visitor = $this->visitor(
            FacialPhotoStatus::Approved,
            [
                $unrelated,
                $configured,
            ],
        );

        $this->assertSame(
            [
                'label' => 'Falha na preparação',
                'color' => 'danger',
            ],
            VisitorFacialPhotoDerivativePresentation::summary(
                $visitor
            )
        );
    }

    public function test_it_integrates_the_read_only_presentation_into_the_ui(): void
    {
        $infolist = file_get_contents(
            base_path(
                'app/Modules/Operations/UI/Filament/'
                .'Resources/VisitorRecords/Schemas/'
                .'VisitorRecordInfolist.php'
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
            $infolist
        );

        $this->assertIsString(
            $table
        );

        $this->assertStringContainsString(
            "TextEntry::make('facial_photo_derivative_status')",
            $infolist
        );

        $this->assertStringContainsString(
            "TextEntry::make('facial_photo_derivative_details')",
            $infolist
        );

        $this->assertStringContainsString(
            "TextColumn::make('facial_photo_derivative_status')",
            $table
        );

        $this->assertStringContainsString(
            "'latestFacialPhoto.derivatives.latestAttempt'",
            $table
        );

        $this->assertStringNotContainsString(
            'source_sha256',
            $infolist
        );

        $this->assertStringNotContainsString(
            'output_metadata',
            $infolist
        );
    }

    /**
     * @param  list<FacialPhotoDerivativeRecord>  $derivatives
     */
    private function visitor(
        FacialPhotoStatus $status,
        array $derivatives = []
    ): VisitorRecord {
        $photo = new FacialPhotoRecord([
            'id' => '11111111-1111-4111-8111-111111111111',
            'status' => $status,
        ]);

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

    private function derivative(
        FacialPhotoDerivativeStatus $status,
        ?FacialPhotoDerivativeAttemptRecord $attempt = null,
        string $profile = 'vanguard_normalized',
        string $policyVersion =
            'vanguard-normalization-v1',
        ?CarbonInterface $createdAt = null,
        ?CarbonInterface $generatedAt = null,
        ?int $width = null,
        ?int $height = null,
        ?string $mimeType = null,
        ?int $sizeBytes = null,
        ?string $failureCode = null,
    ): FacialPhotoDerivativeRecord {
        $record =
            new FacialPhotoDerivativeRecord([
                'id' => (string) fake()->uuid(),
                'profile' => $profile,
                'policy_version' => $policyVersion,
                'status' => $status,
                'width' => $width,
                'height' => $height,
                'mime_type' => $mimeType,
                'size_bytes' => $sizeBytes,
                'generated_at' => $generatedAt,
                'last_failure_code' => $failureCode,
            ]);

        $record->created_at =
            $createdAt ?? now();

        $record->setRelation(
            'latestAttempt',
            $attempt
        );

        return $record;
    }

    private function attempt(
        int $number,
        FacialPhotoDerivativeAttemptStatus $status,
        ?CarbonInterface $startedAt = null,
        ?CarbonInterface $finishedAt = null,
        ?string $failureCode = null,
    ): FacialPhotoDerivativeAttemptRecord {
        return new FacialPhotoDerivativeAttemptRecord([
            'id' => (string) fake()->uuid(),
            'attempt_number' => $number,
            'status' => $status,
            'failure_code' => $failureCode,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
        ]);
    }
}
