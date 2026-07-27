<?php

namespace App\Modules\Operations\Infrastructure\Validation;

use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoResult;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationCommand;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\FacialPhotoValidationExecutor;
use App\Modules\Operations\Application\FacialPhotos\Validation\Schedule\FacialPhotoValidationAfterCommitScheduler;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Connection;
use Throwable;

final readonly class LaravelFacialPhotoValidationAfterCommitScheduler implements FacialPhotoValidationAfterCommitScheduler
{
    public function __construct(
        private bool $enabled,
        private FacialPhotoValidationExecutor $executor,
        private Connection $connection,
        private ExceptionHandler $exceptionHandler,
    ) {}

    public function schedule(
        RegisterVisitorFacialPhotoResult $registration,
        ?int $operatorUserId = null,
    ): bool {
        if (
            ! $this->enabled
            || ! $registration->awaitsAdditionalValidation()
        ) {
            return false;
        }

        $photoId = $registration->photoId;

        $callback = function () use (
            $photoId,
            $operatorUserId
        ): void {
            $this->executeSafely(
                photoId: $photoId,
                operatorUserId: $operatorUserId,
            );
        };

        try {
            if ($this->connection->transactionLevel() === 0) {
                $callback();

                return true;
            }

            $this->connection->afterCommit(
                $callback
            );

            return true;
        } catch (Throwable $exception) {
            $this->reportSafely(
                $exception
            );

            return false;
        }
    }

    private function executeSafely(
        string $photoId,
        ?int $operatorUserId
    ): void {
        try {
            $this->executor->execute(
                new ExecuteFacialPhotoValidationCommand(
                    photoId: $photoId,
                    operatorUserId: $operatorUserId,
                )
            );
        } catch (Throwable $exception) {
            $this->reportSafely(
                $exception
            );
        }
    }

    private function reportSafely(
        Throwable $exception
    ): void {
        try {
            $this->exceptionHandler->report(
                $exception
            );
        } catch (Throwable) {
        }
    }
}
