<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Preview;

use App\Modules\Operations\Application\FacialPhotos\TechnicalAnalysis\AnalyzeFacialPhotoUseCase;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorResolver;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorSelection;
use App\Modules\Operations\Application\FacialPhotos\Validation\ValidateFacialPhotoUseCase;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoPreviewDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalAnalysis;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalIssue;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;
use Throwable;

final readonly class PreviewFacialPhotoUseCase
{
    public function __construct(
        private AnalyzeFacialPhotoUseCase $technicalAnalysis,
        private FacialPhotoValidatorResolver $validatorResolver,
        private bool $facialValidationEnabled,
        private ?string $provider,
        private ?string $scenario,
    ) {}

    public function execute(
        string $absolutePath
    ): FacialPhotoPreviewResult {
        $technical =
            $this->technicalAnalysis->execute(
                $absolutePath
            );

        $fingerprint =
            $this->fingerprintFrom(
                $technical
            );

        $technicalIssues =
            array_map(
                static fn (
                    FacialPhotoTechnicalIssue $issue
                ): FacialPhotoPreviewIssue => FacialPhotoPreviewIssue::fromTechnical(
                    $issue
                ),
                $technical->issues
            );

        if (
            ! $technical->passed
            || $fingerprint === null
        ) {
            if (
                $technicalIssues === []
                && $fingerprint === null
            ) {
                $technicalIssues[] =
                    FacialPhotoPreviewIssue::fromTechnical(
                        FacialPhotoTechnicalIssue::FileUnavailable
                    );
            }

            return new FacialPhotoPreviewResult(
                decision: FacialPhotoPreviewDecision::Rejected,
                fingerprint: $fingerprint,
                technicalAnalysisPassed: false,
                facialValidationPerformed: false,
                issues: $technicalIssues,
            );
        }

        if (
            ! $this->facialValidationEnabled
            || trim((string) $this->provider) === ''
        ) {
            return $this->inconclusiveBecauseValidatorIsUnavailable(
                $fingerprint
            );
        }

        try {
            $selection =
                FacialPhotoValidatorSelection::fromInput(
                    provider: (string) $this->provider,
                    scenario: $this->scenario,
                );

            $validator =
                $this->validatorResolver->resolve(
                    $selection
                );

            $validation =
                (new ValidateFacialPhotoUseCase(
                    $validator
                ))->execute(
                    $absolutePath
                );
        } catch (Throwable) {
            return $this->inconclusiveBecauseValidatorIsUnavailable(
                $fingerprint
            );
        }

        $facialIssues =
            array_map(
                static fn (
                    FacialPhotoValidationIssue $issue
                ): FacialPhotoPreviewIssue => FacialPhotoPreviewIssue::fromFacial(
                    $issue
                ),
                $validation->issues
            );

        $decision = match (
            $validation->decision
        ) {
            FacialPhotoValidationDecision::Approved => FacialPhotoPreviewDecision::Approved,

            FacialPhotoValidationDecision::Rejected => FacialPhotoPreviewDecision::Rejected,

            FacialPhotoValidationDecision::Inconclusive => FacialPhotoPreviewDecision::Inconclusive,
        };

        return new FacialPhotoPreviewResult(
            decision: $decision,
            fingerprint: $fingerprint,
            technicalAnalysisPassed: true,
            facialValidationPerformed: true,
            issues: $facialIssues,
        );
    }

    private function inconclusiveBecauseValidatorIsUnavailable(
        string $fingerprint
    ): FacialPhotoPreviewResult {
        return new FacialPhotoPreviewResult(
            decision: FacialPhotoPreviewDecision::Inconclusive,
            fingerprint: $fingerprint,
            technicalAnalysisPassed: true,
            facialValidationPerformed: false,
            issues: [
                FacialPhotoPreviewIssue::fromFacial(
                    FacialPhotoValidationIssue::ValidatorUnavailable
                ),
            ],
        );
    }

    private function fingerprintFrom(
        FacialPhotoTechnicalAnalysis $analysis
    ): ?string {
        $fingerprint =
            $analysis->metrics['sha256']
                ?? null;

        if (
            ! is_string($fingerprint)
            || preg_match(
                '/\A[a-f0-9]{64}\z/i',
                $fingerprint
            ) !== 1
        ) {
            return null;
        }

        return strtolower(
            $fingerprint
        );
    }
}
