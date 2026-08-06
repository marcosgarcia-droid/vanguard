<?php

declare(strict_types=1);

namespace App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Actions;

use App\Models\User;
use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationCommand;
use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationUseCase;
use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialCredentialSynchronizationRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoDerivativeRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CreateVisitorFacialCredentialSynchronizationAction
{
    public static function make(): Action
    {
        return Action::make(
            'createFacialCredentialSynchronization'
        )
            ->label(
                'Preparar sincronização facial'
            )
            ->tooltip(
                fn (
                    VisitorRecord $record
                ): string => self::tooltip($record)
            )
            ->icon(
                'heroicon-o-link'
            )
            ->iconButton()
            ->color('info')
            ->requiresConfirmation()
            ->closeModalByClickingAway(false)
            ->modalHeading(
                fn (
                    VisitorRecord $record
                ): string => 'Preparar sincronização facial - '
                    .$record->display_name
            )
            ->modalDescription(
                'Esta ação criará somente uma intenção local de sincronização. '
                .'Nenhuma imagem será enviada, nenhuma fila será criada e '
                .'nenhum comando será transmitido ao leitor facial.'
            )
            ->modalSubmitActionLabel(
                'Preparar intenção'
            )
            ->schema([
                Select::make(
                    'access_device_id'
                )
                    ->label(
                        'Leitor facial'
                    )
                    ->options(
                        fn (
                            VisitorRecord $record
                        ): array => VisitorFacialCredentialSynchronizationDeviceSelection::options(
                            $record
                        )
                    )
                    ->helperText(
                        fn (
                            VisitorRecord $record
                        ): string => VisitorFacialCredentialSynchronizationDeviceSelection::unavailableReason(
                            $record
                        )
                    )
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
            ])
            ->visible(
                fn (
                    VisitorRecord $record
                ): bool => ! $record->trashed()
                    && (
                        auth()->user()?->can(
                            'createFacialCredentialSynchronization',
                            $record
                        ) ?? false
                    )
            )
            ->disabled(
                fn (
                    VisitorRecord $record
                ): bool => ! self::isEligibleRecord(
                    $record
                )
                    || VisitorFacialCredentialSynchronizationDeviceSelection::options(
                        $record
                    ) === []
            )
            ->action(
                function (
                    VisitorRecord $record,
                    array $data,
                ): void {
                    $user = auth()->user();

                    if (! $user instanceof User) {
                        Notification::make()
                            ->title(
                                'Não foi possível identificar o operador'
                            )
                            ->body(
                                'Entre novamente no sistema antes de preparar a sincronização facial.'
                            )
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Gate::authorize(
                        'createFacialCredentialSynchronization',
                        $record
                    );

                    $deviceId = trim(
                        (string) (
                            $data['access_device_id']
                                ?? ''
                        )
                    );

                    if (
                        ! VisitorFacialCredentialSynchronizationDeviceSelection::isSelectable(
                            $record,
                            $deviceId
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'access_device_id' => 'Selecione um leitor facial elegível da mesma unidade.',
                        ]);
                    }

                    $device = self::device(
                        $record,
                        $deviceId
                    );

                    if (! $device instanceof AccessDeviceRecord) {
                        throw ValidationException::withMessages([
                            'access_device_id' => 'O leitor facial selecionado não está mais disponível.',
                        ]);
                    }

                    $operation = self::operation(
                        $record,
                        $device
                    );

                    try {
                        $result = app(
                            CreateFacialCredentialSynchronizationUseCase::class
                        )->execute(
                            new CreateFacialCredentialSynchronizationCommand(
                                visitorId: (string) $record->getKey(),

                                accessDeviceId: (string) $device->getKey(),

                                operation: $operation,
                            )
                        );

                        VisitorFacialCredentialSynchronizationCreationAudit::record(
                            visitor: $record,
                            user: $user,
                            device: $device,
                            operation: $operation,
                            result: $result,
                        );

                        self::refresh(
                            $record
                        );

                        $notification =
                            Notification::make()
                                ->title(
                                    VisitorFacialCredentialSynchronizationCreationPresentation::title(
                                        $result
                                    )
                                )
                                ->body(
                                    VisitorFacialCredentialSynchronizationCreationPresentation::message(
                                        result: $result,
                                        operation: $operation,
                                        deviceLabel: (string) $device->display_name,
                                    )
                                );

                        if ($result->isSuccessful()) {
                            $notification
                                ->success()
                                ->send();

                            return;
                        }

                        $notification
                            ->warning()
                            ->persistent()
                            ->send();
                    } catch (Throwable $throwable) {
                        VisitorFacialCredentialSynchronizationCreationAudit::failure(
                            visitor: $record,
                            user: $user,
                            device: $device,
                        );

                        report($throwable);

                        Notification::make()
                            ->title(
                                'Não foi possível preparar a sincronização'
                            )
                            ->body(
                                'Ocorreu uma falha interna. Nenhuma comunicação com o leitor facial foi realizada.'
                            )
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }
            );
    }

    public static function isEligibleRecord(
        VisitorRecord $record
    ): bool {
        if (
            $record->trashed()
            || self::enumValue(
                $record->status
            ) !== 'active'
        ) {
            return false;
        }

        $photo = self::latestPhoto(
            $record
        );

        if (
            ! $photo instanceof FacialPhotoRecord
            || ! $photo->isApproved()
            || ! self::validSha256(
                $photo->sha256
            )
        ) {
            return false;
        }

        return self::currentReadyDerivative(
            $photo
        ) instanceof FacialPhotoDerivativeRecord;
    }

    private static function tooltip(
        VisitorRecord $record
    ): string {
        if (! self::isEligibleRecord($record)) {
            return 'A foto facial precisa estar aprovada e preparada antes da sincronização.';
        }

        if (
            VisitorFacialCredentialSynchronizationDeviceSelection::options(
                $record
            ) === []
        ) {
            return 'Nenhum leitor facial elegível foi localizado nesta unidade.';
        }

        return 'Preparar intenção de sincronização facial';
    }

    private static function device(
        VisitorRecord $visitor,
        string $deviceId,
    ): ?AccessDeviceRecord {
        $device = AccessDeviceRecord::query()
            ->whereKey($deviceId)
            ->where(
                'tenant_id',
                $visitor->tenant_id
            )
            ->where(
                'organization_id',
                $visitor->organization_id
            )
            ->first([
                'id',
                'tenant_id',
                'organization_id',
                'code',
                'name',
                'model',
            ]);

        return $device instanceof AccessDeviceRecord
            ? $device
            : null;
    }

    private static function operation(
        VisitorRecord $visitor,
        AccessDeviceRecord $device,
    ): IntelbrasFacialCredentialOperation {
        $alreadySucceeded =
            FacialCredentialSynchronizationRecord::query()
                ->where(
                    'visitor_id',
                    $visitor->getKey()
                )
                ->where(
                    'access_device_id',
                    $device->getKey()
                )
                ->where(
                    'status',
                    FacialCredentialSynchronizationStatus::Succeeded
                        ->value
                )
                ->exists();

        return $alreadySucceeded
            ? IntelbrasFacialCredentialOperation::Replace
            : IntelbrasFacialCredentialOperation::Register;
    }

    private static function latestPhoto(
        VisitorRecord $record
    ): ?FacialPhotoRecord {
        if (
            $record->relationLoaded(
                'latestFacialPhoto'
            )
        ) {
            $photo = $record->getRelation(
                'latestFacialPhoto'
            );

            return $photo instanceof FacialPhotoRecord
                ? $photo
                : null;
        }

        if (
            ! $record->exists
            || $record->getKey() === null
        ) {
            return null;
        }

        $photo = $record
            ->latestFacialPhoto()
            ->with('derivatives')
            ->first();

        return $photo instanceof FacialPhotoRecord
            ? $photo
            : null;
    }

    private static function currentReadyDerivative(
        FacialPhotoRecord $photo
    ): ?FacialPhotoDerivativeRecord {
        if (
            ! $photo->relationLoaded(
                'derivatives'
            )
        ) {
            if (
                ! $photo->exists
                || $photo->getKey() === null
            ) {
                return null;
            }

            $photo->loadMissing(
                'derivatives'
            );
        }

        $derivatives = $photo->getRelation(
            'derivatives'
        );

        if (! $derivatives instanceof Collection) {
            return null;
        }

        $profile = (string) config(
            'facial_photos.normalization.default_profile',
            'vanguard_normalized'
        );

        $policyVersion = (string) config(
            'facial_photos.normalization.policy_version',
            'vanguard-normalization-v1'
        );

        $sourceSha256 = (string) $photo->sha256;

        $derivative = $derivatives
            ->filter(
                static fn (
                    mixed $candidate
                ): bool => $candidate
                    instanceof FacialPhotoDerivativeRecord
                    && $candidate->status
                        === FacialPhotoDerivativeStatus::Ready
                    && $candidate->profile
                        === $profile
                    && $candidate->policy_version
                        === $policyVersion
                    && $candidate->source_sha256
                        === $sourceSha256
            )
            ->sortByDesc(
                static fn (
                    FacialPhotoDerivativeRecord $candidate
                ): string => sprintf(
                    '%020d|%s',
                    $candidate->generated_at
                        instanceof CarbonInterface
                            ? $candidate
                                ->generated_at
                                ->getTimestamp()
                            : (
                                $candidate->created_at
                                    instanceof CarbonInterface
                                        ? $candidate
                                            ->created_at
                                            ->getTimestamp()
                                        : 0
                            ),
                    (string) $candidate->getKey()
                )
            )
            ->first();

        return $derivative
            instanceof FacialPhotoDerivativeRecord
                ? $derivative
                : null;
    }

    private static function refresh(
        VisitorRecord $record
    ): void {
        $record->unsetRelation(
            'facialCredentialSynchronizations'
        );

        if (
            $record->exists
            && $record->getKey() !== null
        ) {
            $record->load([
                'facialCredentialSynchronizations.accessDevice',
                'facialCredentialSynchronizations.latestAttempt',
            ]);
        }
    }

    private static function enumValue(
        mixed $value
    ): string {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return trim(
            (string) $value
        );
    }

    private static function validSha256(
        mixed $value
    ): bool {
        return is_string($value)
            && preg_match(
                '/^[a-f0-9]{64}$/D',
                $value
            ) === 1;
    }
}
