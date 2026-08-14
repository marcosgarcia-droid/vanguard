<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Storage;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoCommand;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoException;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoResult;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoUseCase;
use App\Modules\Operations\Application\FacialPhotos\Validation\Schedule\FacialPhotoValidationAfterCommitScheduler;
use App\Modules\Operations\Application\FacialPhotos\Validation\Schedule\ScheduleFacialPhotoValidationCommand;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSubjectType;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

final readonly class EmployeeFacialPhotoCaptureRegistrar
{
    public function __construct(
        private RegisterFacialPhotoUseCase $registerFacialPhoto,
        private FacialPhotoMediaCleanup $mediaCleanup,
        private FacialPhotoValidationAfterCommitScheduler $validationScheduler,
    ) {}

    public function register(
        EmployeeRecord $employee,
        UploadedFile $upload,
        string $expectedSha256,
        FacialPhotoSource $source,
        string $confirmationKey,
        string $confirmationContext,
        ?int $createdBy = null,
    ): RegisterFacialPhotoResult {
        return $this->registerUsingContext(
            employee: $employee,
            upload: $upload,
            expectedSha256: $expectedSha256,
            source: $source,
            confirmationKey: $confirmationKey,
            confirmationContext: $confirmationContext,
            expectedConfirmationContext: self::confirmationContext(
                $employee
            ),
            createdBy: $createdBy,
        );
    }

    public function registerFromCreation(
        EmployeeRecord $employee,
        UploadedFile $upload,
        string $expectedSha256,
        FacialPhotoSource $source,
        string $confirmationKey,
        string $confirmationContext,
        ?int $createdBy = null,
    ): RegisterFacialPhotoResult {
        return $this->registerUsingContext(
            employee: $employee,
            upload: $upload,
            expectedSha256: $expectedSha256,
            source: $source,
            confirmationKey: $confirmationKey,
            confirmationContext: $confirmationContext,
            expectedConfirmationContext: self::creationConfirmationContext(),
            createdBy: $createdBy,
        );
    }

    private function registerUsingContext(
        EmployeeRecord $employee,
        UploadedFile $upload,
        string $expectedSha256,
        FacialPhotoSource $source,
        string $confirmationKey,
        string $confirmationContext,
        string $expectedConfirmationContext,
        ?int $createdBy = null,
    ): RegisterFacialPhotoResult {
        if (
            ! hash_equals(
                $expectedConfirmationContext,
                $confirmationContext
            )
        ) {
            throw RegisterFacialPhotoException::invalidConfirmationProof();
        }

        $absolutePath = $upload->getRealPath();

        if (
            ! is_string($absolutePath)
            || trim($absolutePath) === ''
        ) {
            throw RegisterFacialPhotoException::sourceFileUnavailable();
        }

        $originalFileName = basename(
            trim($upload->getClientOriginalName())
        );

        /**
         * Rollback de banco não remove o arquivo físico criado
         * pela Media Library.
         *
         * @var array{
         *     disk: string,
         *     directory: string
         * }|null
         */
        $mediaCleanupReference = null;

        try {
            $result = DB::transaction(
                function () use (
                    $employee,
                    $absolutePath,
                    $originalFileName,
                    $expectedSha256,
                    $source,
                    $confirmationKey,
                    $confirmationContext,
                    $createdBy,
                    &$mediaCleanupReference,
                ): RegisterFacialPhotoResult {
                    $result = $this
                        ->registerFacialPhoto
                        ->execute(
                            new RegisterFacialPhotoCommand(
                                subjectType: FacialPhotoSubjectType::Employee,
                                subjectId: (string) $employee->getKey(),
                                absolutePath: $absolutePath,
                                originalFileName: $originalFileName,
                                expectedSha256: $expectedSha256,
                                source: $source,
                                confirmationKey: $confirmationKey,
                                confirmationContext: $confirmationContext,
                                createdBy: $createdBy,
                                capturedAt: now()
                                    ->toDateTimeImmutable(),
                            )
                        );

                    $photo = FacialPhotoRecord::query()
                        ->find($result->photoId);

                    if (
                        ! $photo
                            instanceof FacialPhotoRecord
                    ) {
                        throw new LogicException(
                            'O registro facial criado não pôde ser localizado.'
                        );
                    }

                    $media = $photo->getFirstMedia(
                        FacialPhotoRecord::ORIGINAL_COLLECTION
                    );

                    if (! $media instanceof Media) {
                        throw new LogicException(
                            'A mídia original da foto facial não foi localizada.'
                        );
                    }

                    $mediaCleanupReference =
                        $this->mediaCleanup->reference(
                            $media
                        );

                    return $result;
                }
            );
        } catch (Throwable $exception) {
            $this->mediaCleanup->remove(
                $mediaCleanupReference
            );

            throw $exception;
        }

        /*
         * Se este coordenador estiver dentro de uma transação
         * externa — por exemplo, uma action Filament — a mídia
         * física também precisa ser removida caso essa transação
         * seja revertida posteriormente.
         */
        $this->registerRollbackCompensation(
            $mediaCleanupReference
        );

        $this->validationScheduler->schedule(
            new ScheduleFacialPhotoValidationCommand(
                photoId: $result->photoId,
                status: $result->status,
                operatorUserId: $createdBy,
            )
        );

        return $result;
    }

    public static function confirmationContext(
        EmployeeRecord $employee
    ): string {
        return 'employee.update.'
            .(string) $employee->getKey()
            .'.photo_capture';
    }

    public static function creationConfirmationContext(): string
    {
        return 'employee.create.photo_capture';
    }

    /**
     * @param array{
     *     disk: string,
     *     directory: string
     * }|null $mediaCleanupReference
     */
    private function registerRollbackCompensation(
        ?array $mediaCleanupReference
    ): void {
        if ($mediaCleanupReference === null) {
            return;
        }

        $connection = DB::connection();

        if ($connection->transactionLevel() === 0) {
            return;
        }

        $connection->afterRollBack(
            function () use (
                $mediaCleanupReference
            ): void {
                $this->mediaCleanup->remove(
                    $mediaCleanupReference
                );
            }
        );
    }
}
