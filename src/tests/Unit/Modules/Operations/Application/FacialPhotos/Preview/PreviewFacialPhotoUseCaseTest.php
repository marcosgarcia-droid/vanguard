<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Preview;

use App\Modules\Operations\Application\FacialPhotos\Preview\PreviewFacialPhotoUseCase;
use App\Modules\Operations\Application\FacialPhotos\TechnicalAnalysis\AnalyzeFacialPhotoUseCase;
use App\Modules\Operations\Application\FacialPhotos\TechnicalAnalysis\FacialPhotoTechnicalAnalyzer;
use App\Modules\Operations\Application\FacialPhotos\Validation\FacialPhotoValidator;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorResolver;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorSelection;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoPreviewDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalAnalysis;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalIssue;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationResult;
use RuntimeException;
use Tests\TestCase;

final class PreviewFacialPhotoUseCaseTest extends TestCase
{
    private const ABSOLUTE_PATH =
        '/tmp/vanguard-facial-photo-preview.jpg';

    private const FINGERPRINT =
        'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
        .'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function test_it_approves_a_technically_valid_and_facially_approved_photo(): void
    {
        $resolver = $this->resolverReturning(
            $this->approvedValidation()
        );

        $result = $this->useCase(
            analysis: $this->passedTechnicalAnalysis(),
            resolver: $resolver,
        )->execute(
            self::ABSOLUTE_PATH
        );

        $this->assertSame(
            FacialPhotoPreviewDecision::Approved,
            $result->decision
        );

        $this->assertSame(
            self::FINGERPRINT,
            $result->fingerprint
        );

        $this->assertTrue(
            $result->canUsePhoto()
        );

        $this->assertTrue(
            $result->technicalAnalysisPassed
        );

        $this->assertTrue(
            $result->facialValidationPerformed
        );

        $this->assertSame(
            [],
            $result->issues
        );

        $presentation =
            $result->presentation();

        $this->assertSame(
            'Foto aprovada',
            $presentation['label']
        );

        $this->assertTrue(
            $presentation['can_use_photo']
        );

        $this->assertArrayNotHasKey(
            'fingerprint',
            $presentation
        );

        $serialized = json_encode(
            $presentation,
            JSON_THROW_ON_ERROR
        );

        foreach (
            [
                'metrics',
                'confidence',
                'face_count',
                'validator',
                'version',
                self::FINGERPRINT,
            ] as $sensitiveValue
        ) {
            $this->assertStringNotContainsString(
                $sensitiveValue,
                $serialized
            );
        }
    }

    public function test_it_rejects_a_photo_that_fails_technical_analysis_without_calling_the_facial_validator(): void
    {
        $resolver = $this->resolverReturning(
            $this->approvedValidation()
        );

        $analysis = new FacialPhotoTechnicalAnalysis(
            version: 'technical-test-v1',
            passed: false,
            metrics: [
                'sha256' => self::FINGERPRINT,
            ],
            issues: [
                FacialPhotoTechnicalIssue::Underexposed,
                FacialPhotoTechnicalIssue::LowSharpness,
            ],
        );

        $result = $this->useCase(
            analysis: $analysis,
            resolver: $resolver,
        )->execute(
            self::ABSOLUTE_PATH
        );

        $this->assertSame(
            FacialPhotoPreviewDecision::Rejected,
            $result->decision
        );

        $this->assertFalse(
            $result->canUsePhoto()
        );

        $this->assertFalse(
            $result->technicalAnalysisPassed
        );

        $this->assertFalse(
            $result->facialValidationPerformed
        );

        $this->assertSame(
            0,
            $resolver->calls
        );

        $this->assertSame(
            [
                'Imagem muito escura',
                'Imagem sem nitidez suficiente',
            ],
            array_map(
                static fn ($issue): string => $issue->label,
                $result->issues
            )
        );
    }

    public function test_it_rejects_a_photo_when_the_facial_validator_reports_a_positioning_problem(): void
    {
        $validation =
            new FacialPhotoValidationResult(
                validator: 'facial-test',
                version: 'facial-test-v1',
                decision: FacialPhotoValidationDecision::Rejected,
                faceCount: 1,
                metrics: [
                    'centered' => false,
                ],
                issues: [
                    FacialPhotoValidationIssue::FaceNotCentered,
                    FacialPhotoValidationIssue::HeadPoseInvalid,
                ],
            );

        $result = $this->useCase(
            analysis: $this->passedTechnicalAnalysis(),
            resolver: $this->resolverReturning(
                $validation
            ),
        )->execute(
            self::ABSOLUTE_PATH
        );

        $this->assertSame(
            FacialPhotoPreviewDecision::Rejected,
            $result->decision
        );

        $this->assertFalse(
            $result->canUsePhoto()
        );

        $this->assertTrue(
            $result->technicalAnalysisPassed
        );

        $this->assertTrue(
            $result->facialValidationPerformed
        );

        $this->assertSame(
            [
                'Rosto fora do centro',
                'Posição da cabeça inadequada',
            ],
            array_map(
                static fn ($issue): string => $issue->label,
                $result->issues
            )
        );
    }

    public function test_it_returns_an_inconclusive_result_when_facial_validation_is_disabled(): void
    {
        $resolver = $this->resolverReturning(
            $this->approvedValidation()
        );

        $result = $this->useCase(
            analysis: $this->passedTechnicalAnalysis(),
            resolver: $resolver,
            validationEnabled: false,
            provider: null,
        )->execute(
            self::ABSOLUTE_PATH
        );

        $this->assertSame(
            FacialPhotoPreviewDecision::Inconclusive,
            $result->decision
        );

        $this->assertFalse(
            $result->canUsePhoto()
        );

        $this->assertTrue(
            $result->technicalAnalysisPassed
        );

        $this->assertFalse(
            $result->facialValidationPerformed
        );

        $this->assertSame(
            0,
            $resolver->calls
        );

        $this->assertSame(
            'Validador indisponível',
            $result->issues[0]->label
        );
    }

    public function test_it_returns_an_inconclusive_result_when_the_provider_cannot_be_resolved(): void
    {
        $resolver =
            $this->resolverThrowing();

        $result = $this->useCase(
            analysis: $this->passedTechnicalAnalysis(),
            resolver: $resolver,
        )->execute(
            self::ABSOLUTE_PATH
        );

        $this->assertSame(
            FacialPhotoPreviewDecision::Inconclusive,
            $result->decision
        );

        $this->assertFalse(
            $result->canUsePhoto()
        );

        $this->assertFalse(
            $result->facialValidationPerformed
        );

        $this->assertSame(
            1,
            $resolver->calls
        );

        $this->assertSame(
            'Validador indisponível',
            $result->issues[0]->label
        );
    }

    public function test_it_preserves_a_validator_inconclusive_decision_without_exposing_metrics(): void
    {
        $validation =
            new FacialPhotoValidationResult(
                validator: 'facial-test',
                version: 'facial-test-v1',
                decision: FacialPhotoValidationDecision::Inconclusive,
                faceCount: 0,
                metrics: [
                    'available' => false,
                ],
                issues: [
                    FacialPhotoValidationIssue::ValidatorUnavailable,
                ],
            );

        $result = $this->useCase(
            analysis: $this->passedTechnicalAnalysis(),
            resolver: $this->resolverReturning(
                $validation
            ),
        )->execute(
            self::ABSOLUTE_PATH
        );

        $this->assertSame(
            FacialPhotoPreviewDecision::Inconclusive,
            $result->decision
        );

        $this->assertFalse(
            $result->canUsePhoto()
        );

        $this->assertTrue(
            $result->facialValidationPerformed
        );

        $presentation =
            $result->presentation();

        $this->assertSame(
            'Validação inconclusiva',
            $presentation['label']
        );

        $this->assertSame(
            'Validador indisponível',
            $presentation['issues'][0]['label']
        );

        $this->assertSame(
            'validator_unavailable',
            $presentation['issues'][0]['code']
        );

        foreach (
            [
                'fingerprint',
                'metrics',
                'face_count',
                'validator',
                'version',
            ] as $sensitiveField
        ) {
            $this->assertArrayNotHasKey(
                $sensitiveField,
                $presentation
            );
        }

        $this->assertArrayNotHasKey(
            'metrics',
            $presentation['issues'][0]
        );
    }

    private function passedTechnicalAnalysis(): FacialPhotoTechnicalAnalysis
    {
        return new FacialPhotoTechnicalAnalysis(
            version: 'technical-test-v1',
            passed: true,
            metrics: [
                'sha256' => self::FINGERPRINT,
                'width' => 720,
                'height' => 900,
                'mean_luminance' => 120.0,
                'sharpness_variance' => 180.0,
            ],
            issues: [],
        );
    }

    private function useCase(
        FacialPhotoTechnicalAnalysis $analysis,
        FacialPhotoValidatorResolver $resolver,
        bool $validationEnabled = true,
        ?string $provider = 'simulator',
        ?string $scenario = 'approved',
    ): PreviewFacialPhotoUseCase {
        $analyzer =
            new class($analysis) implements FacialPhotoTechnicalAnalyzer
            {
                public function __construct(
                    private FacialPhotoTechnicalAnalysis $analysis
                ) {}

                public function analyze(
                    string $absolutePath
                ): FacialPhotoTechnicalAnalysis {
                    return $this->analysis;
                }
            };

        return new PreviewFacialPhotoUseCase(
            technicalAnalysis: new AnalyzeFacialPhotoUseCase(
                $analyzer
            ),
            validatorResolver: $resolver,
            facialValidationEnabled: $validationEnabled,
            provider: $provider,
            scenario: $scenario,
        );
    }

    private function approvedValidation(): FacialPhotoValidationResult
    {
        return new FacialPhotoValidationResult(
            validator: 'facial-test',
            version: 'facial-test-v1',
            decision: FacialPhotoValidationDecision::Approved,
            faceCount: 1,
            metrics: [
                'confidence' => 0.99,
                'centered' => true,
            ],
            issues: [],
        );
    }

    private function resolverReturning(
        FacialPhotoValidationResult $result
    ): FacialPhotoValidatorResolver {
        return new class($result) implements FacialPhotoValidatorResolver
        {
            public int $calls = 0;

            public function __construct(
                private FacialPhotoValidationResult $result
            ) {}

            public function resolve(
                FacialPhotoValidatorSelection $selection
            ): FacialPhotoValidator {
                $this->calls++;

                return new class($this->result) implements FacialPhotoValidator
                {
                    public function __construct(
                        private FacialPhotoValidationResult $result
                    ) {}

                    public function validate(
                        string $absolutePath
                    ): FacialPhotoValidationResult {
                        return $this->result;
                    }
                };
            }
        };
    }

    private function resolverThrowing(): FacialPhotoValidatorResolver
    {
        return new class implements FacialPhotoValidatorResolver
        {
            public int $calls = 0;

            public function resolve(
                FacialPhotoValidatorSelection $selection
            ): FacialPhotoValidator {
                $this->calls++;

                throw new RuntimeException(
                    'Falha sintética do provider.'
                );
            }
        };
    }
}
