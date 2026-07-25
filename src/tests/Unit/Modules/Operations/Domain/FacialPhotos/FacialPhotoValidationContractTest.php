<?php

namespace Tests\Unit\Modules\Operations\Domain\FacialPhotos;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FacialPhotoValidationContractTest extends TestCase
{
    public function test_it_exposes_neutral_validation_decisions(): void
    {
        $this->assertSame(
            [
                'approved' => 'Aprovada',
                'rejected' => 'Reprovada',
                'inconclusive' => 'Validação inconclusiva',
            ],
            FacialPhotoValidationDecision::options()
        );
    }

    public function test_it_exposes_safe_issue_feedback(): void
    {
        $issue =
            FacialPhotoValidationIssue::MultipleFacesDetected;

        $this->assertSame(
            'multiple_faces_detected',
            $issue->value
        );

        $this->assertSame(
            'Mais de um rosto detectado',
            $issue->label()
        );

        $this->assertNotEmpty(
            $issue->guidance()
        );
    }

    public function test_it_represents_an_approved_validation(): void
    {
        $result = new FacialPhotoValidationResult(
            validator: 'synthetic-validator',
            version: 'facial-contract-v1',
            decision: FacialPhotoValidationDecision::Approved,
            faceCount: 1,
            metrics: [
                'confidence' => 0.98,
                'centered' => true,
            ],
            issues: [],
        );

        $this->assertTrue(
            $result->isApproved()
        );

        $this->assertFalse(
            $result->isRejected()
        );

        $this->assertFalse(
            $result->isInconclusive()
        );

        $this->assertSame(
            [],
            $result->issueCodes()
        );

        $this->assertSame(
            'approved',
            $result->toArray()['decision']
        );

        $this->assertSame(
            1,
            $result->toArray()['face_count']
        );
    }

    public function test_it_represents_rejected_and_inconclusive_results(): void
    {
        $rejected = new FacialPhotoValidationResult(
            validator: 'synthetic-validator',
            version: 'facial-contract-v1',
            decision: FacialPhotoValidationDecision::Rejected,
            faceCount: 2,
            metrics: [],
            issues: [
                FacialPhotoValidationIssue::MultipleFacesDetected,
            ],
        );

        $inconclusive = new FacialPhotoValidationResult(
            validator: 'synthetic-validator',
            version: 'facial-contract-v1',
            decision: FacialPhotoValidationDecision::Inconclusive,
            faceCount: 0,
            metrics: [],
            issues: [
                FacialPhotoValidationIssue::ValidatorUnavailable,
            ],
        );

        $this->assertTrue(
            $rejected->isRejected()
        );

        $this->assertTrue(
            $rejected->hasIssue(
                FacialPhotoValidationIssue::MultipleFacesDetected
            )
        );

        $this->assertSame(
            ['multiple_faces_detected'],
            $rejected->issueCodes()
        );

        $this->assertTrue(
            $inconclusive->isInconclusive()
        );

        $this->assertSame(
            'validator_unavailable',
            $inconclusive
                ->toArray()['issues'][0]['code']
        );
    }

    public function test_it_rejects_an_invalid_validator_identity(): void
    {
        $this->assertInvalidResult(
            static fn (): FacialPhotoValidationResult => new FacialPhotoValidationResult(
                validator: ' ',
                version: 'facial-contract-v1',
                decision: FacialPhotoValidationDecision::Approved,
                faceCount: 1,
                metrics: [],
                issues: [],
            )
        );

        $this->assertInvalidResult(
            static fn (): FacialPhotoValidationResult => new FacialPhotoValidationResult(
                validator: 'synthetic-validator',
                version: '',
                decision: FacialPhotoValidationDecision::Approved,
                faceCount: 1,
                metrics: [],
                issues: [],
            )
        );
    }

    public function test_it_rejects_inconsistent_approved_results(): void
    {
        $this->assertInvalidResult(
            static fn (): FacialPhotoValidationResult => new FacialPhotoValidationResult(
                validator: 'synthetic-validator',
                version: 'facial-contract-v1',
                decision: FacialPhotoValidationDecision::Approved,
                faceCount: 0,
                metrics: [],
                issues: [],
            )
        );

        $this->assertInvalidResult(
            static fn (): FacialPhotoValidationResult => new FacialPhotoValidationResult(
                validator: 'synthetic-validator',
                version: 'facial-contract-v1',
                decision: FacialPhotoValidationDecision::Approved,
                faceCount: 1,
                metrics: [],
                issues: [
                    FacialPhotoValidationIssue::FaceNotCentered,
                ],
            )
        );
    }

    public function test_it_requires_an_issue_for_non_approved_results(): void
    {
        foreach (
            [
                FacialPhotoValidationDecision::Rejected,
                FacialPhotoValidationDecision::Inconclusive,
            ] as $decision
        ) {
            $this->assertInvalidResult(
                static fn (): FacialPhotoValidationResult => new FacialPhotoValidationResult(
                    validator: 'synthetic-validator',
                    version: 'facial-contract-v1',
                    decision: $decision,
                    faceCount: 0,
                    metrics: [],
                    issues: [],
                )
            );
        }
    }

    public function test_it_rejects_invalid_counts_metrics_and_issues(): void
    {
        $this->assertInvalidResult(
            static fn (): FacialPhotoValidationResult => new FacialPhotoValidationResult(
                validator: 'synthetic-validator',
                version: 'facial-contract-v1',
                decision: FacialPhotoValidationDecision::Rejected,
                faceCount: -1,
                metrics: [],
                issues: [
                    FacialPhotoValidationIssue::NoFaceDetected,
                ],
            )
        );

        $this->assertInvalidResult(
            static fn (): FacialPhotoValidationResult => new FacialPhotoValidationResult(
                validator: 'synthetic-validator',
                version: 'facial-contract-v1',
                decision: FacialPhotoValidationDecision::Rejected,
                faceCount: 0,
                metrics: [
                    'nested' => [
                        'unsafe',
                    ],
                ],
                issues: [
                    FacialPhotoValidationIssue::NoFaceDetected,
                ],
            )
        );

        $this->assertInvalidResult(
            static fn (): FacialPhotoValidationResult => new FacialPhotoValidationResult(
                validator: 'synthetic-validator',
                version: 'facial-contract-v1',
                decision: FacialPhotoValidationDecision::Rejected,
                faceCount: 0,
                metrics: [],
                issues: [
                    'invalid',
                ],
            )
        );
    }

    /**
     * @param  callable(): FacialPhotoValidationResult  $callback
     */
    public function test_it_restricts_infrastructure_issues_to_inconclusive_results(): void
    {
        $this->assertTrue(
            FacialPhotoValidationIssue::ValidatorUnavailable
                ->requiresInconclusiveDecision()
        );

        $this->assertFalse(
            FacialPhotoValidationIssue::FaceNotCentered
                ->requiresInconclusiveDecision()
        );

        $this->assertInvalidResult(
            static fn (): FacialPhotoValidationResult => new FacialPhotoValidationResult(
                validator: 'synthetic-validator',
                version: 'facial-contract-v1',
                decision: FacialPhotoValidationDecision::Rejected,
                faceCount: 0,
                metrics: [],
                issues: [
                    FacialPhotoValidationIssue::ValidatorUnavailable,
                ],
            )
        );

        $this->assertInvalidResult(
            static fn (): FacialPhotoValidationResult => new FacialPhotoValidationResult(
                validator: 'synthetic-validator',
                version: 'facial-contract-v1',
                decision: FacialPhotoValidationDecision::Inconclusive,
                faceCount: 1,
                metrics: [],
                issues: [
                    FacialPhotoValidationIssue::FaceNotCentered,
                ],
            )
        );
    }

    public function test_it_validates_face_count_against_detection_issues(): void
    {
        $this->assertInvalidResult(
            static fn (): FacialPhotoValidationResult => new FacialPhotoValidationResult(
                validator: 'synthetic-validator',
                version: 'facial-contract-v1',
                decision: FacialPhotoValidationDecision::Rejected,
                faceCount: 1,
                metrics: [],
                issues: [
                    FacialPhotoValidationIssue::NoFaceDetected,
                ],
            )
        );

        $this->assertInvalidResult(
            static fn (): FacialPhotoValidationResult => new FacialPhotoValidationResult(
                validator: 'synthetic-validator',
                version: 'facial-contract-v1',
                decision: FacialPhotoValidationDecision::Rejected,
                faceCount: 1,
                metrics: [],
                issues: [
                    FacialPhotoValidationIssue::MultipleFacesDetected,
                ],
            )
        );
    }

    public function test_it_rejects_duplicate_issues(): void
    {
        $this->assertInvalidResult(
            static fn (): FacialPhotoValidationResult => new FacialPhotoValidationResult(
                validator: 'synthetic-validator',
                version: 'facial-contract-v1',
                decision: FacialPhotoValidationDecision::Rejected,
                faceCount: 0,
                metrics: [],
                issues: [
                    FacialPhotoValidationIssue::NoFaceDetected,
                    FacialPhotoValidationIssue::NoFaceDetected,
                ],
            )
        );
    }

    private function assertInvalidResult(
        callable $callback
    ): void {
        try {
            $callback();

            $this->fail(
                'Era esperada uma InvalidArgumentException.'
            );
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
    }
}
