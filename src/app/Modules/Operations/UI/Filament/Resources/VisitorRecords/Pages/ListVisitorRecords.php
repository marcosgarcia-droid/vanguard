<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Pages;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\UI\Filament\Actions\SelectCurrentTenantFirstAction;
use App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation\ConfirmFacialPhotoPreviewCommand;
use App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation\ConfirmFacialPhotoPreviewException;
use App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation\ConfirmFacialPhotoPreviewUseCase;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Storage\VisitorFacialPhotoCaptureRegistrar;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\VisitorRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ListVisitorRecords extends ListRecords
{
    protected static string $resource = VisitorRecordResource::class;

    private const FACIAL_PHOTO_CONFIRMATION_CONTEXT =
        'visitor.create.photo_capture';

    private ?UploadedFile $pendingPhotoUpload = null;

    protected function getHeaderActions(): array
    {
        return [
            SelectCurrentTenantFirstAction::make(),

            CreateAction::make()
                ->label('Novo visitante')
                ->modalHeading('Novo visitante')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalSubmitActionLabel('Salvar')
                ->createAnother(false)
                ->databaseTransaction()
                ->mutateDataUsing(function (array $data): array {
                    $organization =
                        self::organizationForCreation(
                            $data['organization_id']
                                ?? null
                        );

                    $this->pendingPhotoUpload =
                        self::photoUploadFrom(
                            $data['photo_capture']
                                ?? null
                        );

                    if (
                        $this->pendingPhotoUpload
                            instanceof UploadedFile
                    ) {
                        try {
                            self::confirmPhotoUpload(
                                upload: $this->pendingPhotoUpload,
                                encodedReceipt: $data['photo_capture_receipt']
                                        ?? null,
                                userId: self::authenticatedUserId(),
                            );
                        } catch (
                            ValidationException $exception
                        ) {
                            $this->pendingPhotoUpload =
                                null;

                            throw $exception;
                        }
                    }

                    $data['tenant_id'] =
                        $organization->tenant_id;

                    $data['photo_disk'] = 'local';

                    unset(
                        $data['photo_capture'],
                        $data['photo_capture_receipt'],
                        $data['photo_path'],
                        $data['photo_uploaded_at'],
                    );

                    return $data;
                })
                ->after(
                    function (
                        VisitorRecord $record
                    ): void {
                        $photoUpload =
                            $this->pendingPhotoUpload;

                        try {
                            if (
                                ! $photoUpload
                                    instanceof UploadedFile
                            ) {
                                return;
                            }

                            $createdBy =
                                self::authenticatedUserId();

                            app(
                                VisitorFacialPhotoCaptureRegistrar::class
                            )->register(
                                visitor: $record,
                                upload: $photoUpload,
                                createdBy: $createdBy,
                            );
                        } finally {
                            $this->pendingPhotoUpload =
                                null;
                        }
                    }
                )
                ->successNotificationTitle(
                    'Visitante cadastrado'
                ),
        ];
    }

    private static function confirmPhotoUpload(
        UploadedFile $upload,
        mixed $encodedReceipt,
        ?int $userId,
    ): void {
        $absolutePath =
            $upload->getRealPath();

        try {
            app(
                ConfirmFacialPhotoPreviewUseCase::class
            )->execute(
                new ConfirmFacialPhotoPreviewCommand(
                    encodedReceipt: is_string($encodedReceipt)
                            ? $encodedReceipt
                            : '',
                    absolutePath: is_string($absolutePath)
                            ? $absolutePath
                            : '',
                    expectedStatePath: self::FACIAL_PHOTO_CONFIRMATION_CONTEXT,
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

    private static function authenticatedUserId(): ?int
    {
        $userId = auth()->id();

        return is_numeric($userId)
            ? (int) $userId
            : null;
    }

    private static function organizationForCreation(
        ?string $organizationId
    ): OrganizationRecord {
        if (blank($organizationId)) {
            throw ValidationException::withMessages([
                'organization_id' => 'Selecione a unidade do visitante.',
            ]);
        }

        $organization = OrganizationRecord::query()
            ->whereKey($organizationId)
            ->where('status', 'active')
            ->first();

        if (
            ! $organization
                instanceof OrganizationRecord
        ) {
            throw ValidationException::withMessages([
                'organization_id' => 'A unidade selecionada não está disponível.',
            ]);
        }

        $tenantContext = app(
            TenantContext::class
        );

        $user = auth()->user();

        if (
            ! $tenantContext
                ->hasOrganizationAccess(
                    $user,
                    $organization->id
                )
        ) {
            throw ValidationException::withMessages([
                'organization_id' => 'Você não possui acesso à unidade selecionada.',
            ]);
        }

        $currentTenantId =
            $tenantContext
                ->currentTenantIdForUser($user);

        if (
            filled($currentTenantId)
            && $currentTenantId
                !== $organization->tenant_id
        ) {
            throw ValidationException::withMessages([
                'organization_id' => 'A unidade não pertence ao grupo empresarial selecionado.',
            ]);
        }

        return $organization;
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
