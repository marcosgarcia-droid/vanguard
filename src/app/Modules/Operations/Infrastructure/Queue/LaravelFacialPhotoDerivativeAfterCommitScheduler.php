<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Queue;

use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeCommand;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Schedule\FacialPhotoDerivativeAfterCommitScheduler;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Connection;
use Throwable;

final readonly class LaravelFacialPhotoDerivativeAfterCommitScheduler implements FacialPhotoDerivativeAfterCommitScheduler
{
    /**
     * @param  list<int>  $backoffSeconds
     */
    public function __construct(
        private bool $enabled,
        private Connection $connection,
        private Dispatcher $dispatcher,
        private ExceptionHandler $exceptionHandler,
        private string $queueConnection,
        private string $queue,
        private int $tries,
        private int $timeout,
        private int $uniqueFor,
        private array $backoffSeconds,
    ) {}

    public function schedule(
        GenerateFacialPhotoDerivativeCommand $command
    ): bool {
        if (! $this->enabled) {
            return false;
        }

        $job = new GenerateFacialPhotoDerivativeJob(
            photoId: $command->photoId,
            profile: $command->profile->value,
            policyVersion: $command->policyVersion,
            normalizer: $command->normalizer,
            normalizerVersion: $command->normalizerVersion,
            requestedBy: $command->requestedBy,
            requesterName: $command->requesterName,
            tries: $this->tries,
            timeout: $this->timeout,
            uniqueFor: $this->uniqueFor,
            backoffSeconds: $this->backoffSeconds,
        );

        $job->onConnection(
            $this->queueConnection
        );

        $job->onQueue(
            $this->queue
        );

        try {
            if ($this->connection->transactionLevel() === 0) {
                return $this->dispatchSafely(
                    $job
                );
            }

            $this->connection->afterCommit(
                function () use ($job): void {
                    $this->dispatchSafely(
                        $job
                    );
                }
            );

            return true;
        } catch (Throwable $throwable) {
            $this->reportSafely(
                $throwable
            );

            return false;
        }
    }

    private function dispatchSafely(
        GenerateFacialPhotoDerivativeJob $job
    ): bool {
        try {
            $this->dispatcher->dispatch(
                $job
            );

            return true;
        } catch (Throwable $throwable) {
            $this->reportSafely(
                $throwable
            );

            return false;
        }
    }

    private function reportSafely(
        Throwable $throwable
    ): void {
        try {
            $this->exceptionHandler->report(
                $throwable
            );
        } catch (Throwable) {
        }
    }
}
