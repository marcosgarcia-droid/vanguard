<?php

declare(strict_types=1);

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Actions;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation\ConfirmFacialPhotoPreviewCommand;
use App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation\ConfirmFacialPhotoPreviewException;
use App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation\ConfirmFacialPhotoPreviewUseCase;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoException;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Infrastructure\Storage\EmployeeFacialPhotoCaptureRegistrar;
use App\Modules\Operations\UI\Filament\Forms\Components\FacialPhotoCapture;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UpdateEmployeeFacialPhotoAction
{
    public static function make(): Action
    {
        return Action::make('updateFacialPhoto')
            ->label('Atualizar foto facial')
            ->tooltip('Atualizar foto facial')
            ->icon(Heroicon::OutlinedCamera)
            ->modalHeading(
                fn (
                    EmployeeRecord $record
                ): string => 'Atualizar foto facial - '
                    .$record->display_name
            )
            ->modalDescription(
                'Capture pela webcam ou selecione uma nova imagem. '
                .'A foto anterior permanecerá preservada no histórico biométrico.'
            )
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel('Salvar nova foto')
            ->databaseTransaction()
            ->schema([
                Hidden::make('photo_capture_request_id')
                    ->dehydrated(false),

                Hidden::make('photo_capture_receipt'),

                FacialPhotoCapture::make('photo_capture')
                    ->confirmationContext(
                        fn (
                            EmployeeRecord $record
                        ): string => EmployeeFacialPhotoCaptureRegistrar::confirmationContext(
                            $record
                        )
                    )
                    ->label('Nova foto facial')
                    ->helperText(
                        'Utilize uma imagem recente, nítida, bem iluminada '
                        .'e com somente uma pessoa.'
                    )
                    ->required(),
            ])
            ->visible(
                fn (
                    EmployeeRecord $record
                ): bool => ! $record->trashed()
                    && (
                        auth()->user()?->can(
                            'manageFacialPhoto',
                            $record
                        )
                        ?? false
                    )
            )
            ->action(
                function (
                    EmployeeRecord $record,
                    array $data
                ): void {
                    Gate::authorize(
                        'manageFacialPhoto',
                        $record
                    );

                    if ($record->trashed()) {
                        throw ValidationException::withMessages([
                            'photo_capture' => 'Não é possível atualizar a foto facial '
                                .'de um funcionário excluído.',
                        ]);
                    }

                    $upload = self::photoUploadFrom(
                        $data['photo_capture']
                            ?? null
                    );

                    if (! $upload instanceof UploadedFile) {
                        throw ValidationException::withMessages([
                            'photo_capture' => 'Capture ou selecione uma nova foto facial.',
                        ]);
                    }

                    $createdBy = self::authenticatedUserId();

                    $absolutePath =
                        $upload->getRealPath();

                    $encodedReceipt =
                        $data['photo_capture_receipt']
                            ?? null;

                    try {
                        $confirmation = app(
                            ConfirmFacialPhotoPreviewUseCase::class
                        )->execute(
                            new ConfirmFacialPhotoPreviewCommand(
                                encodedReceipt: is_string($encodedReceipt)
                                    ? $encodedReceipt
                                    : '',
                                absolutePath: is_string($absolutePath)
                                    ? $absolutePath
                                    : '',
                                expectedStatePath: EmployeeFacialPhotoCaptureRegistrar::confirmationContext(
                                    $record
                                ),
                                userId: $createdBy,
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

                    try {
                        app(
                            EmployeeFacialPhotoCaptureRegistrar::class
                        )->register(
                            employee: $record,
                            upload: $upload,
                            expectedSha256: $confirmation->fingerprint,
                            source: self::sourceFrom(
                                $upload
                            ),
                            confirmationKey: $confirmation->confirmationKey,
                            confirmationContext: $confirmation->confirmationContext,
                            createdBy: $createdBy,
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
                }
            )
            ->successNotificationTitle(
                'Foto facial atualizada'
            )
            ->iconButton();
    }

    private static function sourceFrom(
        UploadedFile $upload
    ): FacialPhotoSource {
        $normalizedFileName = strtolower(
            basename(
                trim(
                    $upload->getClientOriginalName()
                )
            )
        );

        return str_starts_with(
            $normalizedFileName,
            'foto-facial-camera-'
        )
            ? FacialPhotoSource::Webcam
            : FacialPhotoSource::FileUpload;
    }

    private static function authenticatedUserId(): ?int
    {
        $userId = auth()->id();

        return is_numeric($userId)
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
