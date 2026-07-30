<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Images\LocalVision;

use App\Modules\Operations\Application\FacialPhotos\Validation\FacialPhotoValidator;
use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoAnalysis;
use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoClient;
use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoClientException;
use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoClientFailure;
use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoPolicy;
use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoPolicyResult;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationResult;
use InvalidArgumentException;
use Throwable;

final readonly class LocalVisionFacialPhotoValidator implements FacialPhotoValidator
{
    public const VALIDATOR = 'local-vision-facial-validator';

    public const VERSION = 'transport-v1';

    public function __construct(
        private ?LocalVisionFacialPhotoClient $client = null,
        private ?LocalVisionFacialPhotoPolicy $policy = null,
    ) {}

    public function validate(
        string $absolutePath
    ): FacialPhotoValidationResult {
        $this->assertAbsolutePath($absolutePath);

        if (! $this->client instanceof LocalVisionFacialPhotoClient) {
            return $this->clientFailureResult(
                LocalVisionFacialPhotoClientFailure::InvalidConfiguration
            );
        }

        try {
            $analysis = $this->client->analyze(
                $absolutePath
            );
        } catch (
            LocalVisionFacialPhotoClientException $exception
        ) {
            return $this->clientFailureResult(
                $exception->failure
            );
        } catch (Throwable) {
            /*
             * A fronteira do provider não expõe falhas técnicas ao operador.
             */
            return $this->unexpectedFailureResult();
        }

        if (! $this->policy instanceof LocalVisionFacialPhotoPolicy) {
            return $this->pendingPolicyResult(
                $analysis
            );
        }

        try {
            return $this->policyResult(
                $analysis,
                $this->policy->decide($analysis)
            );
        } catch (Throwable) {
            /*
             * Uma falha da política nunca pode aprovar ou reprovar a foto.
             */
            return $this->invalidPolicyResult(
                $analysis
            );
        }
    }

    private function policyResult(
        LocalVisionFacialPhotoAnalysis $analysis,
        LocalVisionFacialPhotoPolicyResult $policyResult,
    ): FacialPhotoValidationResult {
        return new FacialPhotoValidationResult(
            validator: self::VALIDATOR,
            version: $policyResult->version,
            decision: $policyResult->decision,
            faceCount: $analysis->faceCount,
            metrics: $this->analysisMetrics(
                $analysis,
                policyConfigured: true
            ),
            issues: $policyResult->issues,
        );
    }

    private function pendingPolicyResult(
        LocalVisionFacialPhotoAnalysis $analysis
    ): FacialPhotoValidationResult {
        return new FacialPhotoValidationResult(
            validator: self::VALIDATOR,
            version: self::VERSION,
            decision: FacialPhotoValidationDecision::Inconclusive,
            faceCount: $analysis->faceCount,
            metrics: $this->analysisMetrics(
                $analysis,
                policyConfigured: false
            ),
            issues: [
                FacialPhotoValidationIssue::ValidationPolicyUnavailable,
            ],
        );
    }

    private function invalidPolicyResult(
        LocalVisionFacialPhotoAnalysis $analysis
    ): FacialPhotoValidationResult {
        return new FacialPhotoValidationResult(
            validator: self::VALIDATOR,
            version: self::VERSION,
            decision: FacialPhotoValidationDecision::Inconclusive,
            faceCount: $analysis->faceCount,
            metrics: [
                ...$this->analysisMetrics(
                    $analysis,
                    policyConfigured: true
                ),
                'failure' => 'invalid_policy_result',
            ],
            issues: [
                FacialPhotoValidationIssue::InvalidValidatorResponse,
            ],
        );
    }

    /**
     * @return array<string, bool|int|float|string|null>
     */
    private function analysisMetrics(
        LocalVisionFacialPhotoAnalysis $analysis,
        bool $policyConfigured,
    ): array {
        return [
            'available' => true,
            'transport_configured' => true,
            'policy_configured' => $policyConfigured,
            'approval_calibrated' => false,
            'service_version' => $analysis->serviceVersion,
            'engine' => $analysis->engine,
            'engine_version' => $analysis->engineVersion,
            ...$analysis->metrics,
        ];
    }

    private function clientFailureResult(
        LocalVisionFacialPhotoClientFailure $failure
    ): FacialPhotoValidationResult {
        $invalidResponse = in_array(
            $failure,
            [
                LocalVisionFacialPhotoClientFailure::RequestRejected,
                LocalVisionFacialPhotoClientFailure::InvalidResponse,
            ],
            true
        );

        return new FacialPhotoValidationResult(
            validator: self::VALIDATOR,
            version: self::VERSION,
            decision: FacialPhotoValidationDecision::Inconclusive,
            faceCount: 0,
            metrics: [
                'available' => $invalidResponse,
                'transport_configured' => $failure
                    !== LocalVisionFacialPhotoClientFailure::InvalidConfiguration,
                'failure' => $failure->value,
            ],
            issues: [
                $invalidResponse
                    ? FacialPhotoValidationIssue::InvalidValidatorResponse
                    : FacialPhotoValidationIssue::ValidatorUnavailable,
            ],
        );
    }

    private function unexpectedFailureResult(): FacialPhotoValidationResult
    {
        return new FacialPhotoValidationResult(
            validator: self::VALIDATOR,
            version: self::VERSION,
            decision: FacialPhotoValidationDecision::Inconclusive,
            faceCount: 0,
            metrics: [
                'available' => false,
                'transport_configured' => true,
                'failure' => 'unexpected_failure',
            ],
            issues: [
                FacialPhotoValidationIssue::InvalidValidatorResponse,
            ],
        );
    }

    private function assertAbsolutePath(
        string $absolutePath
    ): void {
        if (
            trim($absolutePath) === ''
            || ! str_starts_with(
                $absolutePath,
                DIRECTORY_SEPARATOR
            )
        ) {
            throw new InvalidArgumentException(
                'O validador facial local exige um caminho absoluto.'
            );
        }
    }
}
