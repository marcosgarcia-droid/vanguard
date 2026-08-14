<?php

declare(strict_types=1);

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Actions;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation\ConfirmFacialPhotoPreviewCommand;
use App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation\ConfirmFacialPhotoPreviewException;
use App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation\ConfirmFacialPhotoPreviewResult;
use App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation\ConfirmFacialPhotoPreviewUseCase;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoException;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Infrastructure\Storage\EmployeeFacialPhotoCaptureRegistrar;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class EditEmployeeRecordAction
{
    public static function make(): EditAction
    {
        $pendingUpload = null;
        $pendingConfirmation = null;
        $pendingSource = null;

        return EditAction::make()
            ->label('Editar')
            ->tooltip('Editar')
            ->iconButton()
            ->modalHeading(
                fn (
                    EmployeeRecord $record
                ): string => 'Editar funcionário - '
                    .$record->display_name
            )
            ->modalWidth(
                Width::SevenExtraLarge
            )
            ->modalSubmitActionLabel(
                'Salvar alterações'
            )
            ->databaseTransaction()
            ->mutateDataUsing(
                function (
                    array $data,
                    EmployeeRecord $record
                ) use (
                    &$pendingUpload,
                    &$pendingConfirmation,
                    &$pendingSource
                ): array {
                    $pendingUpload =
                        self::photoUploadFrom(
                            $data['photo_capture']
                                ?? null
                        );

                    $pendingConfirmation = null;
                    $pendingSource = null;

                    if (
                        $pendingUpload
                            instanceof UploadedFile
                    ) {
                        Gate::authorize(
                            'manageFacialPhoto',
                            $record
                        );

                        $pendingConfirmation =
                            self::confirmPhotoUpload(
                                record: $record,
                                upload: $pendingUpload,
                                encodedReceipt: $data[
                                    'photo_capture_receipt'
                                ] ?? null,
                                userId: self::authenticatedUserId(),
                            );

                        $pendingSource =
                            self::sourceFrom(
                                $pendingUpload
                            );
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
                ) use (
                    &$pendingUpload,
                    &$pendingConfirmation,
                    &$pendingSource
                ): void {
                    try {
                        if (
                            ! $pendingUpload
                                instanceof UploadedFile
                        ) {
                            return;
                        }

                        Gate::authorize(
                            'manageFacialPhoto',
                            $record
                        );

                        if (
                            ! $pendingConfirmation
                                instanceof ConfirmFacialPhotoPreviewResult
                            || ! $pendingSource
                                instanceof FacialPhotoSource
                        ) {
                            throw ValidationException::withMessages([
                                'photo_capture' => 'A confirmação da foto facial '
                                    .'não está mais disponível. Analise a imagem novamente.',
                            ]);
                        }

                        try {
                            app(
                                EmployeeFacialPhotoCaptureRegistrar::class
                            )->register(
                                employee: $record,
                                upload: $pendingUpload,
                                expectedSha256: $pendingConfirmation
                                    ->fingerprint,
                                source: $pendingSource,
                                confirmationKey: $pendingConfirmation
                                    ->confirmationKey,
                                confirmationContext: $pendingConfirmation
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
                        $pendingUpload = null;
                        $pendingConfirmation = null;
                        $pendingSource = null;
                    }
                }
            )
            ->successNotificationTitle(
                'Funcionário atualizado'
            );
    }

    private static function confirmPhotoUpload(
        EmployeeRecord $record,
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
                    expectedStatePath: EmployeeFacialPhotoCaptureRegistrar::confirmationContext(
                        $record
                    ),
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
