<?php

declare(strict_types=1);

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Actions;

use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationReason;
use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationResult;
use App\Modules\Operations\Application\FacialCredentials\Plan\FacialCredentialSynchronizationPlanningReason;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;

final class EmployeeFacialCredentialSynchronizationCreationPresentation
{
    public static function title(
        CreateFacialCredentialSynchronizationResult $result
    ): string {
        if ($result->wasCreated()) {
            return 'Intenção de sincronização criada';
        }

        if ($result->wasReused()) {
            return 'Intenção existente reutilizada';
        }

        return 'Não foi possível preparar a sincronização';
    }

    public static function message(
        CreateFacialCredentialSynchronizationResult $result,
        IntelbrasFacialCredentialOperation $operation,
        string $deviceLabel,
    ): string {
        $deviceLabel = self::safeText(
            $deviceLabel,
            'Leitor facial selecionado'
        );

        if ($result->wasCreated()) {
            return sprintf(
                'A intenção de %s foi criada para %s, versão %d. '
                .'Nenhuma comunicação com o equipamento foi realizada.',
                mb_strtolower(
                    self::operationLabel($operation)
                ),
                $deviceLabel,
                max(
                    1,
                    (int) $result->version
                )
            );
        }

        if ($result->wasReused()) {
            return sprintf(
                'Uma intenção equivalente de %s para %s já existia '
                .'e foi reutilizada na versão %d. Nenhuma comunicação '
                .'com o equipamento foi realizada.',
                mb_strtolower(
                    self::operationLabel($operation)
                ),
                $deviceLabel,
                max(
                    1,
                    (int) $result->version
                )
            );
        }

        return self::blockedMessage(
            $result
        );
    }

    public static function operationLabel(
        IntelbrasFacialCredentialOperation $operation
    ): string {
        return match ($operation) {
            IntelbrasFacialCredentialOperation::Register => 'Cadastro',

            IntelbrasFacialCredentialOperation::Replace => 'Substituição',
        };
    }

    private static function blockedMessage(
        CreateFacialCredentialSynchronizationResult $result
    ): string {
        return match ($result->reason) {
            CreateFacialCredentialSynchronizationReason::VisitorNotFound => 'O funcionário não foi localizado.',

            CreateFacialCredentialSynchronizationReason::VisitorInactive => 'Somente funcionários ativos podem possuir uma intenção de sincronização facial.',

            CreateFacialCredentialSynchronizationReason::DeviceNotFound => 'O leitor facial selecionado não foi localizado.',

            CreateFacialCredentialSynchronizationReason::DeviceInactive => 'O leitor facial selecionado está inativo.',

            CreateFacialCredentialSynchronizationReason::UnsupportedDevice => 'O dispositivo selecionado não é um leitor facial Intelbras elegível.',

            CreateFacialCredentialSynchronizationReason::ScopeMismatch => 'O leitor facial não pertence ao mesmo grupo empresarial e unidade do funcionário.',

            CreateFacialCredentialSynchronizationReason::CurrentPhotoMissing => 'O funcionário ainda não possui uma foto facial atual.',

            CreateFacialCredentialSynchronizationReason::CurrentPhotoNotApproved => 'A foto facial atual ainda não foi aprovada.',

            CreateFacialCredentialSynchronizationReason::InvalidPhotoMetadata => 'Os dados da foto facial atual não estão válidos para sincronização.',

            CreateFacialCredentialSynchronizationReason::ReadyDerivativeMissing => 'A foto facial ainda não possui uma imagem preparada e pronta.',

            CreateFacialCredentialSynchronizationReason::InvalidDerivativeMetadata => 'Os dados da imagem facial preparada não estão válidos.',

            CreateFacialCredentialSynchronizationReason::SuccessfulConfigurationSnapshotMissing => 'O leitor facial não possui uma leitura válida e atual de modelo e firmware.',

            CreateFacialCredentialSynchronizationReason::PlanningBlocked => self::planningBlockedMessage(
                $result->planningReason
            ),

            CreateFacialCredentialSynchronizationReason::ContextChanged => 'O contexto foi alterado durante a operação. Atualize a página e tente novamente.',

            CreateFacialCredentialSynchronizationReason::Created,
            CreateFacialCredentialSynchronizationReason::Reused => 'A situação da intenção de sincronização não pôde ser determinada.',
        };
    }

    private static function planningBlockedMessage(
        ?FacialCredentialSynchronizationPlanningReason $reason
    ): string {
        return match ($reason) {
            FacialCredentialSynchronizationPlanningReason::MissingModel => 'O modelo do leitor facial não foi informado pela última leitura.',

            FacialCredentialSynchronizationPlanningReason::InvalidModel => 'O modelo informado pelo leitor facial é inválido.',

            FacialCredentialSynchronizationPlanningReason::UnknownModel => 'O modelo do leitor facial ainda não é reconhecido pelo catálogo técnico.',

            FacialCredentialSynchronizationPlanningReason::MissingFirmware => 'A versão de firmware do leitor facial não foi informada.',

            FacialCredentialSynchronizationPlanningReason::InvalidFirmware => 'A versão de firmware informada pelo leitor facial é inválida.',

            FacialCredentialSynchronizationPlanningReason::UnverifiedCombination => 'A combinação de modelo e firmware ainda não possui compatibilidade comprovada.',

            FacialCredentialSynchronizationPlanningReason::UnsupportedOperation => 'O leitor facial não oferece suporte à operação solicitada.',

            FacialCredentialSynchronizationPlanningReason::InvalidCredentialInput => 'Os dados preparados da credencial facial não são aceitos pelo perfil técnico.',

            FacialCredentialSynchronizationPlanningReason::Ready => 'O planejamento retornou uma situação inconsistente.',

            null => 'O planejamento da sincronização facial foi bloqueado de forma preventiva.',
        };
    }

    private static function safeText(
        string $value,
        string $fallback,
    ): string {
        $value = preg_replace(
            '/[\x00-\x1F\x7F]+/u',
            ' ',
            trim($value)
        );

        if (
            ! is_string($value)
            || $value === ''
        ) {
            return $fallback;
        }

        return mb_strimwidth(
            $value,
            0,
            160,
            '…'
        );
    }
}
