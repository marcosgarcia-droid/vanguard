<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Actions;

use App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation\ConfirmFacialPhotoPreviewCommand;
use App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation\ConfirmFacialPhotoPreviewException;
use App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation\ConfirmFacialPhotoPreviewUseCase;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Storage\VisitorFacialPhotoCaptureRegistrar;
use App\Modules\Operations\UI\Filament\Forms\Components\FacialPhotoCapture;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UpdateVisitorFacialPhotoAction
{
    public static function make(): Action
    {
        return Action::make('updateFacialPhoto')
            ->label('Atualizar foto facial')
            ->tooltip('Atualizar foto facial')
            ->icon(Heroicon::OutlinedCamera)
            ->iconButton()
            ->modalHeading(
                fn (
                    VisitorRecord $record
                ): string => 'Atualizar foto facial - '
                    .$record->display_name
            )
            ->modalDescription(
                'Capture pela webcam ou selecione uma nova imagem. '
                .'A foto anterior permanecerá preservada no histórico.'
            )
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel('Salvar nova foto')
            ->databaseTransaction()
            ->schema([
                Hidden::make('photo_capture_receipt'),

                FacialPhotoCapture::make('photo_capture')
                    ->confirmationContext(
                        fn (
                            VisitorRecord $record
                        ): string => self::confirmationContext(
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
                    VisitorRecord $record
                ): bool => ! $record->trashed()
                    && (
                        auth()->user()?->can(
                            'update',
                            $record
                        )
                        ?? false
                    )
            )
            ->action(
                function (
                    VisitorRecord $record,
                    array $data
                ): void {
                    Gate::authorize(
                        'update',
                        $record
                    );

                    if ($record->trashed()) {
                        throw ValidationException::withMessages([
                            'photo_capture' => 'Não é possível atualizar a foto '
                                .'de um visitante excluído.',
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

                    $userId = auth()->id();

                    $createdBy = is_numeric(
                        $userId
                    )
                        ? (int) $userId
                        : null;

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
                                expectedStatePath: self::confirmationContext(
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

                    app(
                        VisitorFacialPhotoCaptureRegistrar::class
                    )->register(
                        visitor: $record,
                        upload: $upload,
                        expectedSha256: $confirmation->fingerprint,
                        createdBy: $createdBy,
                    );

                    $record->unsetRelation(
                        'latestFacialPhoto'
                    );
                }
            )
            ->successNotificationTitle(
                'Foto facial atualizada'
            );
    }

    private static function confirmationContext(
        VisitorRecord $record
    ): string {
        return 'visitor.update.'
            .(string) $record->getKey()
            .'.photo_capture';
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
