<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate;

use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationException;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationResult;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizer;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizerResolver;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeProfile;
use Throwable;

final readonly class GenerateFacialPhotoDerivativeUseCase implements FacialPhotoDerivativeGenerator
{
    public function __construct(
        private GenerateFacialPhotoDerivativeRepository $repository,
        private FacialPhotoNormalizer $normalizer,
        private FacialPhotoDerivativeGenerationGuard $guard,
        private ?FacialPhotoNormalizerResolver $normalizerResolver = null,
    ) {}

    public function execute(
        GenerateFacialPhotoDerivativeCommand $command
    ): GenerateFacialPhotoDerivativeResult {
        $lease = $this->guard->acquire($command);

        $preparation = null;
        $normalization = null;

        try {
            $preparation = $this->repository->prepare(
                $command
            );

            if ($preparation->wasReused()) {
                return $preparation->reusedResult;
            }

            $normalizer = $this->normalizerFor(
                $command
            );

            $normalization = $normalizer->normalize(
                (string) $preparation->absoluteSourcePath
            );

            $this->assertNormalizationMatches(
                $command,
                $preparation,
                $normalization
            );

            return $this->repository->complete(
                $preparation,
                $normalization
            );
        } catch (
            FacialPhotoNormalizationException $exception
        ) {
            $this->recordFailureSafely(
                $preparation,
                $exception->failureCode
            );

            throw GenerateFacialPhotoDerivativeException::normalizationFailed(
                $exception
            );
        } catch (
            GenerateFacialPhotoDerivativeException $exception
        ) {
            $this->recordFailureSafely(
                $preparation,
                $exception->failureCode
            );

            throw $exception;
        } catch (Throwable $throwable) {
            $this->recordFailureSafely(
                $preparation,
                'generation_failed'
            );

            throw GenerateFacialPhotoDerivativeException::persistenceFailed(
                $throwable
            );
        } finally {
            $this->removeTemporaryOutput(
                $normalization
            );

            $lease->release();
        }
    }

    private function normalizerFor(
        GenerateFacialPhotoDerivativeCommand $command
    ): FacialPhotoNormalizer {
        if (
            $this->normalizerResolver
                instanceof FacialPhotoNormalizerResolver
        ) {
            return $this->normalizerResolver->resolve(
                $command->profile
            );
        }

        if (
            $command->profile->equals(
                FacialPhotoDerivativeProfile::vanguardNormalized()
            )
        ) {
            return $this->normalizer;
        }

        throw GenerateFacialPhotoDerivativeException::invalidNormalizerOutput();
    }

    private function assertNormalizationMatches(
        GenerateFacialPhotoDerivativeCommand $command,
        GenerateFacialPhotoDerivativePreparation $preparation,
        FacialPhotoNormalizationResult $normalization,
    ): void {
        if (
            ! $normalization->profile->equals(
                $command->profile
            )
            || ! hash_equals(
                $command->policyVersion,
                $normalization->policyVersion
            )
            || ! hash_equals(
                $command->normalizer,
                $normalization->normalizer
            )
            || ! hash_equals(
                $command->normalizerVersion,
                $normalization->normalizerVersion
            )
            || ! hash_equals(
                $preparation->sourceSha256,
                $normalization->sourceSha256
            )
        ) {
            throw GenerateFacialPhotoDerivativeException::invalidNormalizerOutput();
        }
    }

    private function recordFailureSafely(
        ?GenerateFacialPhotoDerivativePreparation $preparation,
        string $failureCode
    ): void {
        if (
            ! $preparation instanceof GenerateFacialPhotoDerivativePreparation
            || ! $preparation->hasAttempt()
        ) {
            return;
        }

        try {
            $this->repository->fail(
                $preparation,
                $failureCode
            );
        } catch (Throwable) {
        }
    }

    private function removeTemporaryOutput(
        ?FacialPhotoNormalizationResult $normalization
    ): void {
        if (
            ! $normalization instanceof FacialPhotoNormalizationResult
        ) {
            return;
        }

        if (
            is_file($normalization->absolutePath)
            || is_link($normalization->absolutePath)
        ) {
            @unlink($normalization->absolutePath);
        }
    }
}
