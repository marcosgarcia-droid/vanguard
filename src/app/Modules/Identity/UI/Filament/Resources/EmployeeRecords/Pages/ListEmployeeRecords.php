<?php

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Pages;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\UI\Filament\Actions\SelectCurrentTenantFirstAction;
use App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\EmployeeRecordResource;
use App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation\ConfirmFacialPhotoPreviewCommand;
use App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation\ConfirmFacialPhotoPreviewException;
use App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation\ConfirmFacialPhotoPreviewResult;
use App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation\ConfirmFacialPhotoPreviewUseCase;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoException;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Infrastructure\Storage\EmployeeFacialPhotoCaptureRegistrar;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ListEmployeeRecords extends ListRecords
{
    protected static string $resource = EmployeeRecordResource::class;

    private ?UploadedFile $pendingFacialPhotoUpload = null;

    private ?ConfirmFacialPhotoPreviewResult $pendingFacialPhotoConfirmation =
        null;

    protected function getHeaderActions(): array
    {
        return [
            SelectCurrentTenantFirstAction::make(),

            CreateAction::make()
                ->label('Novo funcionário')
                ->modalHeading('Novo funcionário')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalSubmitActionLabel('Salvar')
                ->databaseTransaction()
                ->mutateDataUsing(
                    function (array $data): array {
                        $this->pendingFacialPhotoUpload =
                            self::photoUploadFrom(
                                $data['photo_capture']
                                    ?? null
                            );

                        $this->pendingFacialPhotoConfirmation =
                            null;

                        if (
                            $this->pendingFacialPhotoUpload
                                instanceof UploadedFile
                        ) {
                            if (
                                ! (
                                    auth()->user()?->can(
                                        'ManageFacialPhoto:EmployeeRecord'
                                    )
                                    ?? false
                                )
                            ) {
                                throw ValidationException::withMessages([
                                    'photo_capture' => 'Você não possui permissão '
                                        .'para cadastrar a biometria facial do funcionário.',
                                ]);
                            }

                            try {
                                $this->pendingFacialPhotoConfirmation =
                                    self::confirmPhotoUpload(
                                        upload: $this
                                            ->pendingFacialPhotoUpload,
                                        encodedReceipt: $data[
                                            'photo_capture_receipt'
                                        ] ?? null,
                                        userId: self::authenticatedUserId(),
                                    );
                            } catch (
                                ValidationException $exception
                            ) {
                                $this->pendingFacialPhotoUpload =
                                    null;

                                $this->pendingFacialPhotoConfirmation =
                                    null;

                                throw $exception;
                            }
                        }

                        unset(
                            $data['photo_capture'],
                            $data['photo_capture_receipt'],
                        );

                        return $data;
                    }
                )
                ->after(
                    function (
                        EmployeeRecord $record
                    ): void {
                        $upload =
                            $this->pendingFacialPhotoUpload;

                        $confirmation =
                            $this->pendingFacialPhotoConfirmation;

                        try {
                            if (
                                ! $upload
                                    instanceof UploadedFile
                            ) {
                                return;
                            }

                            Gate::authorize(
                                'manageFacialPhoto',
                                $record
                            );

                            if (
                                ! $confirmation
                                    instanceof ConfirmFacialPhotoPreviewResult
                            ) {
                                throw ValidationException::withMessages([
                                    'photo_capture' => 'A confirmação da foto facial '
                                        .'não está mais disponível. Analise a imagem novamente.',
                                ]);
                            }

                            try {
                                app(
                                    EmployeeFacialPhotoCaptureRegistrar::class
                                )->registerFromCreation(
                                    employee: $record,
                                    upload: $upload,
                                    expectedSha256: $confirmation->fingerprint,
                                    source: self::sourceFrom(
                                        $upload
                                    ),
                                    confirmationKey: $confirmation->confirmationKey,
                                    confirmationContext: $confirmation
                                        ->confirmationContext,
                                    createdBy: self::authenticatedUserId(),
                                );
                            } catch (
                                RegisterFacialPhotoException $exception
                            ) {
                                throw ValidationException::withMessages([
                                    'photo_capture' => $exception->getMessage(),
                                ]);
                            }

                            $record->unsetRelation(
                                'facialPhotos'
                            );

                            $record->unsetRelation(
                                'latestFacialPhoto'
                            );
                        } finally {
                            $this->pendingFacialPhotoUpload =
                                null;

                            $this->pendingFacialPhotoConfirmation =
                                null;
                        }
                    }
                )
                ->successNotificationTitle(
                    'Funcionário cadastrado'
                ),
        ];
    }

    private static function confirmPhotoUpload(
        UploadedFile $upload,
        mixed $encodedReceipt,
        ?int $userId,
    ): ConfirmFacialPhotoPreviewResult {
        $absolutePath =
            $upload->getRealPath();

        try {
            return app(
                ConfirmFacialPhotoPreviewUseCase::class
            )->execute(
                new ConfirmFacialPhotoPreviewCommand(
                    encodedReceipt: is_string(
                        $encodedReceipt
                    )
                        ? $encodedReceipt
                        : '',
                    absolutePath: is_string(
                        $absolutePath
                    )
                        ? $absolutePath
                        : '',
                    expectedStatePath: EmployeeFacialPhotoCaptureRegistrar::creationConfirmationContext(),
                    userId: $userId,
                    confirmedAt: now()
                        ->toDateTimeImmutable(),
                )
            );
        } catch (
            ConfirmFacialPhotoPreviewException $exception
        ) {
            throw ValidationException::withMessages([
                'photo_capture' => $exception->getMessage(),
            ]);
        }
    }

    private static function sourceFrom(
        UploadedFile $upload
    ): FacialPhotoSource {
        $fileName = strtolower(
            basename(
                trim(
                    $upload->getClientOriginalName()
                )
            )
        );

        return str_starts_with(
            $fileName,
            'foto-facial-camera-'
        )
            ? FacialPhotoSource::Webcam
            : FacialPhotoSource::FileUpload;
    }

    private static function authenticatedUserId(): ?int
    {
        $userId = auth()->id();

        return is_numeric(
            $userId
        )
            ? (int) $userId
            : null;
    }

    private static function photoUploadFrom(
        mixed $value
    ): ?UploadedFile {
        if ($value instanceof UploadedFile) {
            return $value;
        }

        if (! is_array($value)) {
            return null;
        }

        foreach ($value as $file) {
            if ($file instanceof UploadedFile) {
                return $file;
            }
        }

        return null;
    }
}
