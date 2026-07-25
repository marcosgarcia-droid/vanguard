<?php

namespace App\Modules\Operations\Application\FacialPhotos\Validation\Execute;

use App\Modules\Operations\Application\FacialPhotos\Validation\ValidateFacialPhotoUseCase;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatusTransitionException;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatusTransitionPolicy;
use Throwable;

final readonly class ExecuteFacialPhotoValidationUseCase
{
    public function __construct(
        private ExecuteFacialPhotoValidationRepository $repository,
        private ValidateFacialPhotoUseCase $validateFacialPhoto,
        private FacialPhotoStatusTransitionPolicy $transitionPolicy,
    ) {}

    public function execute(
        ExecuteFacialPhotoValidationCommand $command
    ): ExecuteFacialPhotoValidationResult {
        try {
            $target = $this->repository->findTarget(
                $command->photoId
            );
        } catch (
            ExecuteFacialPhotoValidationException $exception
        ) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ExecuteFacialPhotoValidationException::preparationFailed(
                $exception
            );
        }

        if (! $target instanceof FacialPhotoValidationTarget) {
            throw ExecuteFacialPhotoValidationException::photoNotFound();
        }

        if ($target->photoId !== $command->photoId) {
            throw ExecuteFacialPhotoValidationException::targetMismatch();
        }

        if (
            $target->status
            !== FacialPhotoStatus::PendingValidation
        ) {
            throw ExecuteFacialPhotoValidationException::statusNotEligible(
                $target->status
            );
        }

        /*
         * O validador permanece fora da transação de persistência.
         * Assim, uma futura validação demorada não mantém locks
         * de banco durante processamento de imagem ou integração.
         */
        try {
            $validation =
                $this->validateFacialPhoto->execute(
                    $target->absolutePath
                );
        } catch (Throwable $exception) {
            throw ExecuteFacialPhotoValidationException::validationFailed(
                $exception
            );
        }

        try {
            $transition =
                $this->transitionPolicy->transition(
                    currentStatus: $target->status,
                    decision: $validation->decision,
                );
        } catch (
            FacialPhotoStatusTransitionException $exception
        ) {
            throw ExecuteFacialPhotoValidationException::statusNotEligible(
                status: $target->status,
                previous: $exception,
            );
        }

        try {
            return $this->repository->persist(
                new FacialPhotoValidationPersistenceData(
                    target: $target,
                    validation: $validation,
                    transition: $transition,
                    operatorUserId: $command->operatorUserId,
                )
            );
        } catch (
            ExecuteFacialPhotoValidationException $exception
        ) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ExecuteFacialPhotoValidationException::persistenceFailed(
                $exception
            );
        }
    }
}
