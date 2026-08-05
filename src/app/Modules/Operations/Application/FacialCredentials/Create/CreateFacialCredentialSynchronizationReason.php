<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialCredentials\Create;

enum CreateFacialCredentialSynchronizationReason: string
{
    case Created = 'created';
    case Reused = 'reused';

    case VisitorNotFound = 'visitor_not_found';
    case VisitorInactive = 'visitor_inactive';

    case DeviceNotFound = 'device_not_found';
    case DeviceInactive = 'device_inactive';
    case UnsupportedDevice = 'unsupported_device';

    case ScopeMismatch = 'scope_mismatch';

    case CurrentPhotoMissing = 'current_photo_missing';
    case CurrentPhotoNotApproved = 'current_photo_not_approved';
    case InvalidPhotoMetadata = 'invalid_photo_metadata';

    case ReadyDerivativeMissing = 'ready_derivative_missing';
    case InvalidDerivativeMetadata = 'invalid_derivative_metadata';

    case SuccessfulConfigurationSnapshotMissing =
        'successful_configuration_snapshot_missing';

    case PlanningBlocked = 'planning_blocked';
    case ContextChanged = 'context_changed';

    public function isSuccessful(): bool
    {
        return in_array(
            $this,
            [
                self::Created,
                self::Reused,
            ],
            true
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Intenção criada',

            self::Reused => 'Intenção existente reutilizada',

            self::VisitorNotFound => 'Visitante não localizado',

            self::VisitorInactive => 'Visitante inativo',

            self::DeviceNotFound => 'Dispositivo não localizado',

            self::DeviceInactive => 'Dispositivo inativo',

            self::UnsupportedDevice => 'Dispositivo não elegível',

            self::ScopeMismatch => 'Contexto operacional incompatível',

            self::CurrentPhotoMissing => 'Foto facial atual não localizada',

            self::CurrentPhotoNotApproved => 'Foto facial atual não aprovada',

            self::InvalidPhotoMetadata => 'Metadados da foto atual inválidos',

            self::ReadyDerivativeMissing => 'Imagem facial preparada não localizada',

            self::InvalidDerivativeMetadata => 'Metadados da imagem preparada inválidos',

            self::SuccessfulConfigurationSnapshotMissing => 'Leitura válida de modelo e firmware não localizada',

            self::PlanningBlocked => 'Planejamento facial bloqueado',

            self::ContextChanged => 'Contexto alterado durante a criação',
        };
    }
}
