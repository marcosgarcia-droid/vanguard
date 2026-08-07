<?php

declare(strict_types=1);

namespace App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Actions;

use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationReason;
use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationResult;
use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationAttemptStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialCredentialSynchronizationRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use BackedEnum;

final class VisitorFacialCredentialSynchronizationExecutionPresentation
{
    /**
     * @return array<string, string>
     */
    public static function options(
        VisitorRecord $visitor
    ): array {
        return VisitorFacialCredentialSynchronizationExecutionEligibility::synchronizations(
            $visitor
        )
            ->mapWithKeys(
                static fn (
                    FacialCredentialSynchronizationRecord $synchronization
                ): array => [
                    (string) $synchronization->getKey() => self::synchronizationLabel(
                        $synchronization
                    ),
                ]
            )
            ->all();
    }

    public static function synchronizationLabel(
        FacialCredentialSynchronizationRecord $synchronization
    ): string {
        if (
            ! $synchronization->relationLoaded(
                'accessDevice'
            )
            && $synchronization->exists
        ) {
            $synchronization->loadMissing(
                'accessDevice'
            );
        }

        $device = $synchronization->getRelation(
            'accessDevice'
        );

        $deviceLabel = $device instanceof AccessDeviceRecord
            ? self::deviceLabel($device)
            : 'Leitor facial';

        return sprintf(
            '%s — %s — versão %d',
            $deviceLabel,
            self::operationLabel(
                $synchronization->operation
            ),
            max(
                1,
                (int) $synchronization->version
            )
        );
    }

    /**
     * @return array{
     *     level: 'success'|'info'|'warning'|'danger',
     *     title: string,
     *     body: string
     * }
     */
    public static function fromResult(
        ExecuteFacialCredentialSynchronizationResult $result
    ): array {
        if (! $result->reason->isSuccessful()) {
            return [
                'level' => 'warning',
                'title' => 'Sincronização não executada',
                'body' => $result->reason->label(),
            ];
        }

        $reusedPrefix = $result->reason
            === ExecuteFacialCredentialSynchronizationReason::Reused
                ? 'O resultado já existente foi reutilizado. '
                : '';

        $attempt = max(
            1,
            (int) $result->attemptNumber
        );

        $provider = self::providerLabel(
            $result->provider
        );

        $scenario = self::scenarioLabel(
            $result->scenario
        );

        $context = sprintf(
            'Tentativa %d — %s%s',
            $attempt,
            $provider,
            $scenario === null
                ? ''
                : ' — '.$scenario
        );

        return match ($result->status) {
            FacialCredentialSynchronizationAttemptStatus::Succeeded => [
                'level' => 'success',
                'title' => 'Sincronização facial simulada',
                'body' => $reusedPrefix
                    .$context
                    .'. A resposta sintética foi concluída com sucesso.',
            ],

            FacialCredentialSynchronizationAttemptStatus::RequiresAttention => [
                'level' => 'warning',
                'title' => 'Sincronização requer atenção',
                'body' => $reusedPrefix
                    .$context
                    .'. O cenário sintético requer análise.',
            ],

            FacialCredentialSynchronizationAttemptStatus::Blocked => [
                'level' => 'warning',
                'title' => 'Sincronização bloqueada',
                'body' => $reusedPrefix
                    .$context
                    .'. A execução foi bloqueada de forma segura.',
            ],

            FacialCredentialSynchronizationAttemptStatus::Failed => [
                'level' => 'danger',
                'title' => 'Falha na sincronização simulada',
                'body' => $reusedPrefix
                    .$context
                    .'. O cenário sintético retornou falha.',
            ],

            FacialCredentialSynchronizationAttemptStatus::Pending,
            FacialCredentialSynchronizationAttemptStatus::Processing => [
                'level' => 'info',
                'title' => 'Sincronização em processamento',
                'body' => $reusedPrefix.$context.'.',
            ],

            null => [
                'level' => 'warning',
                'title' => 'Situação da sincronização indisponível',
                'body' => $result->reason->label(),
            ],
        };
    }

    /**
     * @return array{
     *     level: 'warning',
     *     title: string,
     *     body: string
     * }
     */
    public static function unavailable(
        string $message
    ): array {
        return [
            'level' => 'warning',
            'title' => 'Execução facial indisponível',
            'body' => trim($message) !== ''
                ? trim($message)
                : 'A sincronização não está disponível para execução.',
        ];
    }

    private static function operationLabel(
        mixed $operation
    ): string {
        $value = $operation instanceof BackedEnum
            ? $operation->value
            : (string) $operation;

        return match (strtolower(trim($value))) {
            'register' => 'Cadastrar face',
            'replace' => 'Substituir face',
            default => 'Operação facial',
        };
    }

    private static function deviceLabel(
        AccessDeviceRecord $device
    ): string {
        foreach ([
            $device->name ?? null,
            $device->code ?? null,
        ] as $candidate) {
            if (
                is_string($candidate)
                && trim($candidate) !== ''
            ) {
                return trim($candidate);
            }
        }

        return 'Leitor facial';
    }

    private static function providerLabel(
        ?string $provider
    ): string {
        return match (
            strtolower(trim((string) $provider))
        ) {
            'simulator' => 'Simulador local',
            'disabled' => 'Execução desativada',
            'vanguard' => 'VANGUARD',
            default => 'Provider não identificado',
        };
    }

    private static function scenarioLabel(
        ?string $scenario
    ): ?string {
        return match (
            strtolower(trim((string) $scenario))
        ) {
            'succeeded' => 'Sucesso sintético',
            'duplicate_photo' => 'Foto duplicada sintética',
            'failed' => 'Falha sintética',
            'invalid_response' => 'Resposta sintética inválida',
            default => null,
        };
    }
}
