<?php

namespace App\Modules\Operations\Infrastructure\Storage;

use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoCommand;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoResult;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoUseCase;
use App\Modules\Operations\Application\FacialPhotos\Validation\Schedule\FacialPhotoValidationAfterCommitScheduler;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

final readonly class VisitorFacialPhotoCaptureRegistrar
{
    public function __construct(
        private VisitorPhotoUploadStorage $legacyStorage,
        private RegisterVisitorFacialPhotoUseCase $registerFacialPhoto,
        private FacialPhotoMediaCleanup $mediaCleanup,
        private FacialPhotoValidationAfterCommitScheduler $validationScheduler,
    ) {}

    public function register(
        VisitorRecord $visitor,
        UploadedFile $upload,
        string $expectedSha256,
        ?int $createdBy = null,
    ): RegisterVisitorFacialPhotoResult {
        $legacyPath = null;

        /**
         * @var array{
         *     disk: string,
         *     directory: string
         * }|null
         */
        $facialMediaReference = null;

        $originalFileName = basename(
            trim($upload->getClientOriginalName())
        );

        $source = $this->sourceFor(
            $originalFileName
        );

        try {
            $result = DB::transaction(
                function () use (
                    $visitor,
                    $upload,
                    $expectedSha256,
                    $createdBy,
                    $originalFileName,
                    $source,
                    &$legacyPath,
                    &$facialMediaReference,
                ): RegisterVisitorFacialPhotoResult {
                    $legacyPath =
                        $this->legacyStorage->store(
                            $upload
                        );

                    $uploadedAt = now();

                    $visitor->forceFill([
                        'photo_disk' => 'local',
                        'photo_path' => $legacyPath,
                        'photo_uploaded_at' => $uploadedAt,
                    ])->save();

                    $absolutePath = Storage::disk(
                        'local'
                    )->path($legacyPath);

                    $result = $this
                        ->registerFacialPhoto
                        ->execute(
                            new RegisterVisitorFacialPhotoCommand(
                                visitorId: (string) $visitor->getKey(),
                                absolutePath: $absolutePath,
                                originalFileName: $originalFileName,
                                expectedSha256: $expectedSha256,
                                source: $source,
                                createdBy: $createdBy,
                                capturedAt: $uploadedAt
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

                    $facialMediaReference =
                        $this->mediaCleanup->reference(
                            $media
                        );

                    return $result;
                }
            );

            /*
             * Quando o coordenador foi executado dentro de outra
             * transação — como a transação da action do Filament —
             * os registros ainda podem ser revertidos posteriormente.
             *
             * O callback remove os arquivos físicos caso essa
             * transação externa seja revertida.
             */
            $this->registerRollbackCompensation(
                $legacyPath,
                $facialMediaReference
            );

            $this->validationScheduler->schedule(
                registration: $result,
                operatorUserId: $createdBy,
            );

            return $result;
        } catch (Throwable $exception) {
            $this->removeLegacyPhoto(
                $legacyPath
            );

            $this->mediaCleanup->remove(
                $facialMediaReference
            );

            throw $exception;
        }
    }

    /**
     * @param array{
     *     disk: string,
     *     directory: string
     * }|null $facialMediaReference
     */
    private function registerRollbackCompensation(
        ?string $legacyPath,
        ?array $facialMediaReference
    ): void {
        if (
            blank($legacyPath)
            && $facialMediaReference === null
        ) {
            return;
        }

        $connection = DB::connection();

        /*
         * Nível zero significa que a transação própria do
         * coordenador já foi confirmada e não há transação
         * externa capaz de reverter os registros.
         */
        if ($connection->transactionLevel() === 0) {
            return;
        }

        $connection->afterRollBack(
            function () use (
                $legacyPath,
                $facialMediaReference
            ): void {
                $this->removeLegacyPhoto(
                    $legacyPath
                );

                $this->mediaCleanup->remove(
                    $facialMediaReference
                );
            }
        );
    }

    private function removeLegacyPhoto(
        ?string $legacyPath
    ): void {
        if (blank($legacyPath)) {
            return;
        }

        try {
            Storage::disk('local')
                ->delete($legacyPath);
        } catch (Throwable $cleanupException) {
            report($cleanupException);
        }
    }

    private function sourceFor(
        string $originalFileName
    ): FacialPhotoSource {
        $normalizedFileName = strtolower(
            basename($originalFileName)
        );

        if (
            str_starts_with(
                $normalizedFileName,
                'visitante-camera-'
            )
        ) {
            return FacialPhotoSource::Webcam;
        }

        return FacialPhotoSource::FileUpload;
    }
}
