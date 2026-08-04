<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Queue;

use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeCommand;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Schedule\FacialPhotoDerivativeAfterCommitScheduler;
use App\Modules\Operations\Infrastructure\Queue\GenerateFacialPhotoDerivativeJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LaravelFacialPhotoDerivativeAfterCommitSchedulerTest extends TestCase
{
    protected function tearDown(): void
    {
        while (
            DB::connection()->transactionLevel() > 0
        ) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_it_remains_disabled_by_default(): void
    {
        Bus::fake();

        config()->set(
            'facial_photos.normalization.async_generation.enabled',
            false
        );

        $scheduled = app(
            FacialPhotoDerivativeAfterCommitScheduler::class
        )->schedule(
            $this->command()
        );

        $this->assertFalse(
            $scheduled
        );

        Bus::assertNotDispatched(
            GenerateFacialPhotoDerivativeJob::class
        );
    }

    public function test_it_dispatches_only_after_commit(): void
    {
        Bus::fake();

        config()->set(
            'facial_photos.normalization.async_generation.enabled',
            true
        );

        $scheduler = app(
            FacialPhotoDerivativeAfterCommitScheduler::class
        );

        DB::beginTransaction();

        $this->assertTrue(
            $scheduler->schedule(
                $this->command()
            )
        );

        Bus::assertNotDispatched(
            GenerateFacialPhotoDerivativeJob::class
        );

        DB::commit();

        Bus::assertDispatched(
            GenerateFacialPhotoDerivativeJob::class,
            static fn (
                GenerateFacialPhotoDerivativeJob $job
            ): bool => $job->connection === 'redis'
                && $job->queue === 'default'
        );
    }

    public function test_rollback_discards_the_pending_dispatch(): void
    {
        Bus::fake();

        config()->set(
            'facial_photos.normalization.async_generation.enabled',
            true
        );

        $scheduler = app(
            FacialPhotoDerivativeAfterCommitScheduler::class
        );

        DB::beginTransaction();

        $this->assertTrue(
            $scheduler->schedule(
                $this->command()
            )
        );

        DB::rollBack();

        Bus::assertNotDispatched(
            GenerateFacialPhotoDerivativeJob::class
        );
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
