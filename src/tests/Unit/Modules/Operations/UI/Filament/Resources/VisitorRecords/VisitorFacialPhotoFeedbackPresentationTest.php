<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalIssue;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoValidationAttemptRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Schemas\VisitorFacialPhotoStatusPresentation;
use PHPUnit\Framework\TestCase;

final class VisitorFacialPhotoFeedbackPresentationTest extends TestCase
{
    public function test_it_presents_recognized_technical_and_validation_feedback(): void
    {
        $visitor = $this->visitorWithPhoto(
            status: FacialPhotoStatus::Rejected,
            technicalIssues: [
                FacialPhotoTechnicalIssue::ResolutionTooLow->value,
                FacialPhotoTechnicalIssue::LowSharpness->value,
                'unknown_technical_code',
                123,
            ],
            validationIssues: [
                FacialPhotoValidationIssue::FaceOutsideFrame->value,
                'unknown_validation_code',
                null,
            ]
        );

        $this->assertSame(
            [
                'Resolução insuficiente — Utilize uma imagem com pelo menos 500 × 500 pixels.',
                'Imagem sem nitidez suficiente — Mantenha a câmera firme e aguarde o foco antes de capturar.',
                'Rosto fora do enquadramento — Mantenha cabeça, queixo e laterais do rosto dentro da imagem.',
            ],
            VisitorFacialPhotoStatusPresentation::feedback(
                $visitor
            )
        );
    }

    public function test_it_presents_safe_guidance_for_an_inconclusive_validation(): void
    {
        $visitor = $this->visitorWithPhoto(
            status: FacialPhotoStatus::PendingValidation,
            validationIssues: [
                FacialPhotoValidationIssue::ValidatorUnavailable->value,
            ]
        );

        $this->assertSame(
            [
                'Validador indisponível — A validação não pôde ser concluída. Tente novamente posteriormente.',
            ],
            VisitorFacialPhotoStatusPresentation::feedback(
                $visitor
            )
        );
    }

    public function test_it_hides_stale_feedback_for_terminal_non_rejected_statuses(): void
    {
        foreach (
            [
                FacialPhotoStatus::Approved,
                FacialPhotoStatus::Outdated,
            ] as $status
        ) {
            $visitor = $this->visitorWithPhoto(
                status: $status,
                technicalIssues: [
                    FacialPhotoTechnicalIssue::LowContrast->value,
                ],
                validationIssues: [
                    FacialPhotoValidationIssue::FaceNotCentered->value,
                ]
            );

            $this->assertSame(
                [],
                VisitorFacialPhotoStatusPresentation::feedback(
                    $visitor
                )
            );
        }
    }

    public function test_the_infolist_uses_safe_bulleted_feedback(): void
    {
        $infolist = file_get_contents(
            dirname(__DIR__, 8)
            .'/app/Modules/Operations/UI/Filament/Resources/'
            .'VisitorRecords/Schemas/VisitorRecordInfolist.php'
        );

        $this->assertIsString(
            $infolist
        );

        $this->assertStringContainsString(
            "TextEntry::make('facial_photo_feedback')",
            $infolist
        );

        $this->assertStringContainsString(
            "->label('Orientações para a foto facial')",
            $infolist
        );

        $this->assertStringContainsString(
            '->listWithLineBreaks()',
            $infolist
        );

        $this->assertStringContainsString(
            '->bulleted()',
            $infolist
        );

        $this->assertStringContainsString(
            'VisitorFacialPhotoStatusPresentation::feedback(',
            $infolist
        );

        foreach (
            [
                'metrics',
                'face_count',
                'confidence',
                'sha256',
                'validation_result',
                'rejection_reasons',
                'validator_version',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $infolist
            );
        }
    }

    /**
     * @param  list<mixed>  $technicalIssues
     * @param  list<mixed>  $validationIssues
     */
    private function visitorWithPhoto(
        FacialPhotoStatus $status,
        array $technicalIssues = [],
        array $validationIssues = []
    ): VisitorRecord {
        $attempt =
            new FacialPhotoValidationAttemptRecord;

        $attempt->setAttribute(
            'issues',
            $validationIssues
        );

        $photo =
            new FacialPhotoRecord;

        $photo->setAttribute(
            'status',
            $status
        );

        $photo->setAttribute(
            'rejection_reasons',
            $technicalIssues
        );

        $photo->setRelation(
            'latestValidationAttempt',
            $attempt
        );

        $visitor =
            new VisitorRecord;

        $visitor->setRelation(
            'latestFacialPhoto',
            $photo
        );

        return $visitor;
    }
}
