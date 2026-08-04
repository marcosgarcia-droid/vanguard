<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Queue;

use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\FacialPhotoDerivativeGenerator;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeCommand;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeResult;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Infrastructure\Queue\GenerateFacialPhotoDerivativeJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Tests\TestCase;

final class GenerateFacialPhotoDerivativeJobTest extends TestCase
{
    public function test_it_queues_only_scalar_context_and_executes_the_generator(): void
    {
        $job = $this->job();

        $this->assertInstanceOf(
            ShouldQueue::class,
            $job
        );

        $this->assertInstanceOf(
            ShouldBeUnique::class,
            $job
        );

        $generator = $this->createMock(
            FacialPhotoDerivativeGenerator::class
        );

        $generator
            ->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(
                    static fn (
                        GenerateFacialPhotoDerivativeCommand $command
                    ): bool => $command->photoId
                        === $job->photoId
                        && $command->profile->value
                            === 'vanguard_normalized'
                )
            )
            ->willReturn(
                new GenerateFacialPhotoDerivativeResult(
                    derivativeId: '22222222-2222-4222-8222-222222222222',
                    attemptId: '33333333-3333-4333-8333-333333333333',
                    status: FacialPhotoDerivativeStatus::Ready,
                    reused: false,
                    mediaId: 1,
                    width: 600,
                    height: 900,
                    mimeType: 'image/jpeg',
                    sizeBytes: 100_000,
                    sha256: str_repeat('a', 64),
                )
            );

        $job->handle(
            $generator
        );

        $restored = unserialize(
            serialize($job)
        );

        $this->assertInstanceOf(
            GenerateFacialPhotoDerivativeJob::class,
            $restored
        );

        $this->assertSame(
            $job->uniqueId(),
            $restored->uniqueId()
        );

        $this->assertSame(
            [10, 30, 60],
            $job->backoff()
        );
    }

    public function test_unique_identity_changes_with_the_policy(): void
    {
        $first = $this->job();

        $second = new GenerateFacialPhotoDerivativeJob(
            photoId: $first->photoId,
            profile: $first->profile,
            policyVersion: 'vanguard-normalization-v2',
            normalizer: $first->normalizer,
            normalizerVersion: $first->normalizerVersion,
            requestedBy: null,
            requesterName: null,
            tries: 3,
            timeout: 120,
            uniqueFor: 600,
            backoffSeconds: [10, 30, 60],
        );

        $this->assertNotSame(
            $first->uniqueId(),
            $second->uniqueId()
        );
    }

    private function job(): GenerateFacialPhotoDerivativeJob
    {
        return new GenerateFacialPhotoDerivativeJob(
            photoId: '11111111-1111-4111-8111-111111111111',
            profile: 'vanguard_normalized',
            policyVersion: 'vanguard-normalization-v1',
            normalizer: 'spatie-gd',
            normalizerVersion: 'spatie-gd-v1',
            requestedBy: null,
            requesterName: 'Operador sintético',
            tries: 3,
            timeout: 120,
            uniqueFor: 600,
            backoffSeconds: [10, 30, 60],
        );
    }
}
