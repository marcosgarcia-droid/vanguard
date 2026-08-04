<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Concurrency;

use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeCommand;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeException;
use App\Modules\Operations\Infrastructure\Concurrency\CacheFacialPhotoDerivativeGenerationGuard;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class CacheFacialPhotoDerivativeGenerationGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'cache.default',
            'array'
        );

        Cache::store('array')->flush();
    }

    public function test_it_blocks_concurrent_generation_and_releases_safely(): void
    {
        $guard =
            new CacheFacialPhotoDerivativeGenerationGuard(
                300
            );

        $command = $this->command();

        $first = $guard->acquire(
            $command
        );

        try {
            $guard->acquire(
                $command
            );

            $this->fail(
                'A segunda execução deveria ser bloqueada.'
            );
        } catch (
            GenerateFacialPhotoDerivativeException $exception
        ) {
            $this->assertSame(
                'generation_in_progress',
                $exception->failureCode
            );
        }

        $first->release();
        $first->release();

        $second = $guard->acquire(
            $command
        );

        $second->release();

        $this->addToAssertionCount(1);
    }

    private function command(): GenerateFacialPhotoDerivativeCommand
    {
        return new GenerateFacialPhotoDerivativeCommand(
            photoId: '11111111-1111-4111-8111-111111111111',
            profile: 'vanguard_normalized',
            policyVersion: 'vanguard-normalization-v1',
            normalizer: 'spatie-gd',
            normalizerVersion: 'spatie-gd-v1',
        );
    }
}
