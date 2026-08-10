<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoCommand;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoException;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoFailure;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoRepository;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoCommand;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoException;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoRepository;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoResult;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSubjectType;

final readonly class EloquentRegisterVisitorFacialPhotoRepository implements RegisterVisitorFacialPhotoRepository
{
    public function __construct(
        private RegisterFacialPhotoRepository $repository,
    ) {}

    public function register(
        RegisterVisitorFacialPhotoCommand $command
    ): RegisterVisitorFacialPhotoResult {
        /*
         * Compatibilidade com a ordem de validação do fluxo legado:
         * o repository antigo verificava a origem antes de consultar
         * o visitante.
         */
        if (
            ! is_file($command->absolutePath)
            || ! is_readable($command->absolutePath)
        ) {
            throw RegisterVisitorFacialPhotoException::sourceFileUnavailable();
        }

        try {
            $result = $this->repository->register(
                new RegisterFacialPhotoCommand(
                    subjectType: FacialPhotoSubjectType::Visitor,
                    subjectId: $command->visitorId,
                    absolutePath: $command->absolutePath,
                    originalFileName: $command->originalFileName,
                    expectedSha256: $command->expectedSha256,
                    source: $command->source,
                    confirmationKey: $command->confirmationKey,
                    confirmationContext: $command->confirmationContext,
                    createdBy: $command->createdBy,
                    capturedAt: $command->capturedAt,
                )
            );
        } catch (RegisterFacialPhotoException $exception) {
            throw $this->legacyException(
                $exception
            );
        }

        return new RegisterVisitorFacialPhotoResult(
            photoId: $result->photoId,
            status: $result->status,
            technicalAnalysis: $result->technicalAnalysis,
        );
    }

    private function legacyException(
        RegisterFacialPhotoException $exception
    ): RegisterVisitorFacialPhotoException {
        return match ($exception->failure) {
            RegisterFacialPhotoFailure::InvalidSubject,
            RegisterFacialPhotoFailure::SubjectNotFound => RegisterVisitorFacialPhotoException::visitorNotFound(),

            RegisterFacialPhotoFailure::SourceFileUnavailable => RegisterVisitorFacialPhotoException::sourceFileUnavailable(),

            RegisterFacialPhotoFailure::InvalidExpectedFingerprint => RegisterVisitorFacialPhotoException::invalidExpectedFingerprint(),

            RegisterFacialPhotoFailure::DefinitiveFingerprintUnavailable => RegisterVisitorFacialPhotoException::definitiveFingerprintUnavailable(),

            RegisterFacialPhotoFailure::DefinitiveFingerprintMismatch => RegisterVisitorFacialPhotoException::definitiveFingerprintMismatch(),

            RegisterFacialPhotoFailure::InvalidConfirmationProof => RegisterVisitorFacialPhotoException::invalidConfirmationProof(),

            RegisterFacialPhotoFailure::ConfirmationAlreadyConsumed => RegisterVisitorFacialPhotoException::confirmationAlreadyConsumed(
                $exception->getPrevious()
                    ?? $exception
            ),

            RegisterFacialPhotoFailure::RegistrationFailed => RegisterVisitorFacialPhotoException::registrationFailed(
                $exception->getPrevious()
                    ?? $exception
            ),

            RegisterFacialPhotoFailure::SubjectUnavailable => RegisterVisitorFacialPhotoException::registrationFailed(
                $exception
            ),
        };
    }
}
