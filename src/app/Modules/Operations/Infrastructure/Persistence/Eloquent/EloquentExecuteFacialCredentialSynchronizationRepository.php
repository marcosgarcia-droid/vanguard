<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationCommand;
use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationReason;
use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationRepository;
use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationResult;
use App\Modules\Operations\Application\FacialCredentials\Plan\PlanFacialCredentialSynchronizationCommand;
use App\Modules\Operations\Application\FacialCredentials\Plan\PlanFacialCredentialSynchronizationUseCase;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationAttemptStatus;
use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\DisabledIntelbrasFacialCredentialSynchronizer;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialPlan;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizationResult;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizer;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizerResolver;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\SimulatedIntelbrasFacialCredentialSynchronizationScenario;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\SimulatedIntelbrasFacialCredentialSynchronizer;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use JsonException;
use Throwable;

final class EloquentExecuteFacialCredentialSynchronizationRepository implements ExecuteFacialCredentialSynchronizationRepository
{
    public function __construct(
        private readonly PlanFacialCredentialSynchronizationUseCase $planner,
        private readonly IntelbrasFacialCredentialSynchronizerResolver $resolver,
    ) {}

    public function execute(
        ExecuteFacialCredentialSynchronizationCommand $command
    ): ExecuteFacialCredentialSynchronizationResult {
        return DB::transaction(
            function () use (
                $command
            ): ExecuteFacialCredentialSynchronizationResult {
                $synchronization =
                    FacialCredentialSynchronizationRecord::query()
                        ->whereKey(
                            $command->synchronizationId
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    ! $synchronization instanceof FacialCredentialSynchronizationRecord
                ) {
                    return ExecuteFacialCredentialSynchronizationResult::withoutAttempt(
                        ExecuteFacialCredentialSynchronizationReason::SynchronizationNotFound
                    );
                }

                $latestAttempt =
                    $synchronization
                        ->attempts()
                        ->orderByDesc('attempt_number')
                        ->lockForUpdate()
                        ->first();

                if (
                    $latestAttempt instanceof FacialCredentialSynchronizationAttemptRecord
                ) {
                    if (
                        $synchronization->status
                            === FacialCredentialSynchronizationStatus::Pending
                    ) {
                        return ExecuteFacialCredentialSynchronizationResult::withoutAttempt(
                            reason: ExecuteFacialCredentialSynchronizationReason::InconsistentState,
                            synchronizationId: (string) $synchronization->getKey(),
                        );
                    }

                    return ExecuteFacialCredentialSynchronizationResult::reused(
                        synchronizationId: (string) $synchronization->getKey(),
                        attemptNumber: (int) $latestAttempt->attempt_number,
                        status: $latestAttempt->status,
                        provider: (string) $latestAttempt->provider,
                        scenario: $latestAttempt->scenario,
                        failureCode: $latestAttempt->failure_code,
                    );
                }

                if (
                    $synchronization->status
                        === FacialCredentialSynchronizationStatus::Superseded
                ) {
                    return ExecuteFacialCredentialSynchronizationResult::withoutAttempt(
                        reason: ExecuteFacialCredentialSynchronizationReason::SynchronizationSuperseded,
                        synchronizationId: (string) $synchronization->getKey(),
                    );
                }

                if (
                    $synchronization->status
                        !== FacialCredentialSynchronizationStatus::Pending
                ) {
                    return ExecuteFacialCredentialSynchronizationResult::withoutAttempt(
                        reason: ExecuteFacialCredentialSynchronizationReason::SynchronizationNotExecutable,
                        synchronizationId: (string) $synchronization->getKey(),
                    );
                }

                $attemptNumber = 1;
                $startedAt = CarbonImmutable::now();
                $startedNanoseconds = hrtime(true);

                $preparation = $this->preparePlanLocked(
                    $synchronization
                );

                if (
                    ! $preparation['plan'] instanceof IntelbrasFacialCredentialPlan
                ) {
                    return $this->finalizeAttempt(
                        synchronization: $synchronization,
                        attemptNumber: $attemptNumber,
                        attemptStatus: FacialCredentialSynchronizationAttemptStatus::Blocked,
                        synchronizationStatus: FacialCredentialSynchronizationStatus::Blocked,
                        provider: 'vanguard',
                        scenario: null,
                        failureCode: $preparation['failure_code'],
                        message: $preparation['message'],
                        startedAt: $startedAt,
                        startedNanoseconds: $startedNanoseconds,
                    );
                }

                $plan = $preparation['plan'];

                try {
                    $synchronizer =
                        $this->resolver->resolve();
                } catch (Throwable) {
                    return $this->finalizeAttempt(
                        synchronization: $synchronization,
                        attemptNumber: $attemptNumber,
                        attemptStatus: FacialCredentialSynchronizationAttemptStatus::Failed,
                        synchronizationStatus: FacialCredentialSynchronizationStatus::Failed,
                        provider: 'vanguard',
                        scenario: null,
                        failureCode: 'resolver_exception',
                        message: 'Não foi possível resolver o sincronizador seguro.',
                        startedAt: $startedAt,
                        startedNanoseconds: $startedNanoseconds,
                    );
                }

                $providerContext =
                    $this->providerContext(
                        $synchronizer
                    );

                if ($providerContext === null) {
                    return $this->finalizeAttempt(
                        synchronization: $synchronization,
                        attemptNumber: $attemptNumber,
                        attemptStatus: FacialCredentialSynchronizationAttemptStatus::Blocked,
                        synchronizationStatus: FacialCredentialSynchronizationStatus::Blocked,
                        provider: 'vanguard',
                        scenario: null,
                        failureCode: 'provider_not_allowed',
                        message: 'O sincronizador configurado não é permitido nesta etapa.',
                        startedAt: $startedAt,
                        startedNanoseconds: $startedNanoseconds,
                    );
                }

                try {
                    $integrationResult =
                        $synchronizer->synchronize(
                            $plan
                        );
                } catch (Throwable) {
                    return $this->finalizeAttempt(
                        synchronization: $synchronization,
                        attemptNumber: $attemptNumber,
                        attemptStatus: FacialCredentialSynchronizationAttemptStatus::Failed,
                        synchronizationStatus: FacialCredentialSynchronizationStatus::Failed,
                        provider: $providerContext['provider'],
                        scenario: $providerContext['scenario'],
                        failureCode: 'synchronizer_exception',
                        message: 'A execução segura do sincronizador não foi concluída.',
                        startedAt: $startedAt,
                        startedNanoseconds: $startedNanoseconds,
                    );
                }

                $mapped = $this->mapResult(
                    result: $integrationResult,
                    expectedPlanFingerprint: (string) $synchronization
                        ->plan_fingerprint,
                    provider: $providerContext['provider'],
                );

                return $this->finalizeAttempt(
                    synchronization: $synchronization,
                    attemptNumber: $attemptNumber,
                    attemptStatus: $mapped['attempt_status'],
                    synchronizationStatus: $mapped['synchronization_status'],
                    provider: $providerContext['provider'],
                    scenario: $providerContext['scenario'],
                    failureCode: $mapped['failure_code'],
                    message: $mapped['message'],
                    startedAt: $startedAt,
                    startedNanoseconds: $startedNanoseconds,
                );
            },
            3
        );
    }

    /**
     * @return array{
     *     plan: IntelbrasFacialCredentialPlan|null,
     *     failure_code: string|null,
     *     message: string
     * }
     */
    private function preparePlanLocked(
        FacialCredentialSynchronizationRecord $synchronization
    ): array {
        $operation =
            IntelbrasFacialCredentialOperation::tryFrom(
                trim(
                    (string) $synchronization->operation
                )
            );

        if ($operation === null) {
            return $this->blockedPreparation(
                'invalid_operation',
                'A operação facial registrada é inválida.'
            );
        }

        $visitor =
            VisitorRecord::query()
                ->whereKey(
                    $synchronization->visitor_id
                )
                ->lockForUpdate()
                ->first();

        if (! $visitor instanceof VisitorRecord) {
            return $this->blockedPreparation(
                'visitor_not_found',
                'O visitante da intenção não foi localizado.'
            );
        }

        if (
            $this->enumValue(
                $visitor->status
            ) !== 'active'
        ) {
            return $this->blockedPreparation(
                'visitor_inactive',
                'O visitante não está ativo.'
            );
        }

        $device =
            AccessDeviceRecord::query()
                ->whereKey(
                    $synchronization->access_device_id
                )
                ->lockForUpdate()
                ->first();

        if (! $device instanceof AccessDeviceRecord) {
            return $this->blockedPreparation(
                'device_not_found',
                'O dispositivo da intenção não foi localizado.'
            );
        }

        if (
            $device->status
                !== AccessDeviceStatus::Active
        ) {
            return $this->blockedPreparation(
                'device_inactive',
                'O dispositivo não está ativo.'
            );
        }

        if (! $this->isSupportedDevice($device)) {
            return $this->blockedPreparation(
                'unsupported_device',
                'O dispositivo não é elegível para a sincronização facial.'
            );
        }

        if (
            ! $this->sameScope(
                $visitor,
                $device
            )
            || ! $this->sameScope(
                $visitor,
                $synchronization
            )
        ) {
            return $this->blockedPreparation(
                'scope_mismatch',
                'O contexto operacional da intenção é incompatível.'
            );
        }

        $photo =
            $visitor
                ->facialPhotos()
                ->orderByDesc('captured_at')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

        if (
            ! $photo instanceof FacialPhotoRecord
            || (string) $photo->getKey()
                !== (string) $synchronization
                    ->facial_photo_id
        ) {
            return $this->blockedPreparation(
                'current_photo_changed',
                'A foto facial atual foi alterada.'
            );
        }

        if (
            $photo->status
                !== FacialPhotoStatus::Approved
            || ! $this->sameScope(
                $visitor,
                $photo
            )
            || ! $this->validSha256(
                $photo->sha256
            )
        ) {
            return $this->blockedPreparation(
                'current_photo_invalid',
                'A foto facial atual não está válida.'
            );
        }

        $derivative =
            $photo
                ->derivatives()
                ->where(
                    'status',
                    FacialPhotoDerivativeStatus::Ready
                        ->value
                )
                ->where(
                    'source_sha256',
                    (string) $photo->sha256
                )
                ->orderByDesc('generated_at')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

        if (
            ! $derivative instanceof FacialPhotoDerivativeRecord
            || (string) $derivative->getKey()
                !== (string) $synchronization
                    ->facial_photo_derivative_id
        ) {
            return $this->blockedPreparation(
                'derivative_changed',
                'A imagem facial preparada foi alterada.'
            );
        }

        if (
            ! $this->sameScope(
                $visitor,
                $derivative
            )
            || ! $this->validDerivative(
                $derivative
            )
        ) {
            return $this->blockedPreparation(
                'derivative_invalid',
                'A imagem facial preparada não está válida.'
            );
        }

        $snapshot =
            AccessDeviceConfigurationSnapshotRecord::query()
                ->where(
                    'access_device_id',
                    $device->getKey()
                )
                ->where(
                    'status',
                    AccessDeviceConfigurationReadStatus::Success
                        ->value
                )
                ->whereNotNull('device_model')
                ->whereNotNull('firmware_version')
                ->where('device_model', '<>', '')
                ->where('firmware_version', '<>', '')
                ->orderByDesc('read_at')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

        if (
            ! $snapshot instanceof AccessDeviceConfigurationSnapshotRecord
            || ! $this->sameScope(
                $visitor,
                $snapshot
            )
            || ! $this->deviceModelMatchesSnapshot(
                $device,
                $snapshot
            )
        ) {
            return $this->blockedPreparation(
                'snapshot_missing',
                'Uma leitura válida do dispositivo não foi localizada.'
            );
        }

        $planning = $this->planner->execute(
            new PlanFacialCredentialSynchronizationCommand(
                deviceModel: trim(
                    (string) $snapshot->device_model
                ),
                firmwareVersion: trim(
                    (string) $snapshot->firmware_version
                ),
                operation: $operation,
                externalUserId: (string) $visitor->getKey(),
                displayName: trim(
                    (string) $visitor->display_name
                ),
                photoSha256: (string) $derivative->sha256,
                photoSizeBytes: (int) $derivative->size_bytes,
                photoWidth: (int) $derivative->width,
                photoHeight: (int) $derivative->height,
                photoMimeType: (string) $derivative->mime_type,
            )
        );

        if (
            ! $planning->isReady()
            || ! $planning->plan instanceof IntelbrasFacialCredentialPlan
        ) {
            return $this->blockedPreparation(
                failureCode: 'planning_'.$planning->reason->value,
                message: 'O planejamento facial permanece bloqueado.'
            );
        }

        try {
            $planFingerprint =
                $planning->planFingerprint();
        } catch (JsonException) {
            return $this->blockedPreparation(
                'plan_fingerprint_invalid',
                'Não foi possível validar o plano facial.'
            );
        }

        if (
            ! is_string($planFingerprint)
            || ! $this->validSha256(
                $planFingerprint
            )
            || ! hash_equals(
                (string) $synchronization
                    ->plan_fingerprint,
                $planFingerprint
            )
        ) {
            return $this->blockedPreparation(
                'plan_fingerprint_mismatch',
                'O plano facial foi alterado.'
            );
        }

        $contextFingerprint =
            $this->contextFingerprint(
                synchronization: $synchronization,
                operation: $operation,
                planFingerprint: $planFingerprint,
            );

        if (
            ! hash_equals(
                (string) $synchronization
                    ->context_fingerprint,
                $contextFingerprint
            )
        ) {
            return $this->blockedPreparation(
                'context_fingerprint_mismatch',
                'O contexto da intenção foi alterado.'
            );
        }

        return [
            'plan' => $planning->plan,
            'failure_code' => null,
            'message' => 'O contexto da intenção foi validado.',
        ];
    }

    /**
     * @return array{
     *     plan: null,
     *     failure_code: string,
     *     message: string
     * }
     */
    private function blockedPreparation(
        string $failureCode,
        string $message,
    ): array {
        return [
            'plan' => null,
            'failure_code' => substr(
                $failureCode,
                0,
                64
            ),
            'message' => substr(
                $message,
                0,
                255
            ),
        ];
    }

    /**
     * @return array{
     *     provider: string,
     *     scenario: string|null
     * }|null
     */
    private function providerContext(
        IntelbrasFacialCredentialSynchronizer $synchronizer
    ): ?array {
        if (
            $synchronizer instanceof DisabledIntelbrasFacialCredentialSynchronizer
        ) {
            return [
                'provider' => 'disabled',
                'scenario' => null,
            ];
        }

        if (
            ! $synchronizer instanceof SimulatedIntelbrasFacialCredentialSynchronizer
        ) {
            return null;
        }

        $scenario =
            SimulatedIntelbrasFacialCredentialSynchronizationScenario::tryFrom(
                strtolower(
                    trim(
                        (string) config(
                            'intelbras_facial_synchronization.simulator.scenario'
                        )
                    )
                )
            );

        if ($scenario === null) {
            return null;
        }

        return [
            'provider' => 'simulator',
            'scenario' => $scenario->value,
        ];
    }

    /**
     * @return array{
     *     attempt_status: FacialCredentialSynchronizationAttemptStatus,
     *     synchronization_status: FacialCredentialSynchronizationStatus,
     *     failure_code: string|null,
     *     message: string
     * }
     */
    private function mapResult(
        IntelbrasFacialCredentialSynchronizationResult $result,
        string $expectedPlanFingerprint,
        string $provider,
    ): array {
        if ($result->transportAttempted) {
            return $this->failedMapping(
                'transport_not_allowed',
                'O resultado indicou transporte não permitido.'
            );
        }

        if ($provider === 'disabled') {
            return [
                'attempt_status' => FacialCredentialSynchronizationAttemptStatus::Blocked,
                'synchronization_status' => FacialCredentialSynchronizationStatus::Blocked,
                'failure_code' => 'provider_disabled',
                'message' => 'A sincronização facial permanece desativada.',
            ];
        }

        if (
            ! is_string(
                $result->planFingerprint
            )
            || ! hash_equals(
                $expectedPlanFingerprint,
                $result->planFingerprint
            )
        ) {
            return $this->failedMapping(
                'result_fingerprint_mismatch',
                'O resultado não corresponde ao plano validado.'
            );
        }

        if ($result->wasSimulatedSuccessfully()) {
            return [
                'attempt_status' => FacialCredentialSynchronizationAttemptStatus::Succeeded,
                'synchronization_status' => FacialCredentialSynchronizationStatus::Succeeded,
                'failure_code' => null,
                'message' => 'A sincronização facial foi concluída somente no simulador.',
            ];
        }

        if ($result->isDuplicatePhoto()) {
            return [
                'attempt_status' => FacialCredentialSynchronizationAttemptStatus::RequiresAttention,
                'synchronization_status' => FacialCredentialSynchronizationStatus::RequiresAttention,
                'failure_code' => 'duplicate_photo',
                'message' => 'O simulador indicou que a foto facial já estava cadastrada.',
            ];
        }

        return $this->failedMapping(
            'simulation_failed',
            'A simulação facial não foi concluída com sucesso.'
        );
    }

    /**
     * @return array{
     *     attempt_status: FacialCredentialSynchronizationAttemptStatus,
     *     synchronization_status: FacialCredentialSynchronizationStatus,
     *     failure_code: string,
     *     message: string
     * }
     */
    private function failedMapping(
        string $failureCode,
        string $message,
    ): array {
        return [
            'attempt_status' => FacialCredentialSynchronizationAttemptStatus::Failed,
            'synchronization_status' => FacialCredentialSynchronizationStatus::Failed,
            'failure_code' => $failureCode,
            'message' => $message,
        ];
    }

    private function finalizeAttempt(
        FacialCredentialSynchronizationRecord $synchronization,
        int $attemptNumber,
        FacialCredentialSynchronizationAttemptStatus $attemptStatus,
        FacialCredentialSynchronizationStatus $synchronizationStatus,
        string $provider,
        ?string $scenario,
        ?string $failureCode,
        string $message,
        CarbonImmutable $startedAt,
        int $startedNanoseconds,
    ): ExecuteFacialCredentialSynchronizationResult {
        $completedAt = CarbonImmutable::now();

        $durationMilliseconds = max(
            0,
            (int) floor(
                (
                    hrtime(true)
                    - $startedNanoseconds
                ) / 1_000_000
            )
        );

        $attempt =
            $synchronization
                ->attempts()
                ->create([
                    'attempt_number' => $attemptNumber,
                    'status' => $attemptStatus,
                    'provider' => substr(
                        $provider,
                        0,
                        32
                    ),
                    'scenario' => $scenario === null
                            ? null
                            : substr(
                                $scenario,
                                0,
                                64
                            ),
                    'failure_code' => $failureCode === null
                            ? null
                            : substr(
                                $failureCode,
                                0,
                                64
                            ),
                    'message' => substr(
                        $message,
                        0,
                        255
                    ),
                    'duration_ms' => $durationMilliseconds,
                    'plan_fingerprint' => (string) $synchronization
                        ->plan_fingerprint,
                    'started_at' => $startedAt,
                    'completed_at' => $completedAt,
                ]);

        $synchronization->status =
            $synchronizationStatus;

        $synchronization->save();

        return ExecuteFacialCredentialSynchronizationResult::executed(
            synchronizationId: (string) $synchronization->getKey(),
            attemptNumber: (int) $attempt->attempt_number,
            status: $attemptStatus,
            provider: (string) $attempt->provider,
            scenario: $attempt->scenario,
            failureCode: $attempt->failure_code,
        );
    }

    private function contextFingerprint(
        FacialCredentialSynchronizationRecord $synchronization,
        IntelbrasFacialCredentialOperation $operation,
        string $planFingerprint,
    ): string {
        $serialized = json_encode(
            [
                'version' => 1,
                'tenant_id' => (string) $synchronization->tenant_id,
                'organization_id' => (string) $synchronization->organization_id,
                'visitor_id' => (string) $synchronization->visitor_id,
                'facial_photo_id' => (string) $synchronization->facial_photo_id,
                'facial_photo_derivative_id' => (string) $synchronization
                    ->facial_photo_derivative_id,
                'access_device_id' => (string) $synchronization
                    ->access_device_id,
                'operation' => $operation->value,
                'plan_fingerprint' => $planFingerprint,
            ],
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
        );

        return hash(
            'sha256',
            $serialized
        );
    }

    private function validDerivative(
        FacialPhotoDerivativeRecord $derivative
    ): bool {
        return $derivative->status
                === FacialPhotoDerivativeStatus::Ready
            && $this->validSha256(
                $derivative->source_sha256
            )
            && $this->validSha256(
                $derivative->sha256
            )
            && (int) $derivative->size_bytes > 0
            && (int) $derivative->width > 0
            && (int) $derivative->height > 0
            && trim(
                (string) $derivative->mime_type
            ) !== '';
    }

    private function isSupportedDevice(
        AccessDeviceRecord $device
    ): bool {
        return strtolower(
            trim(
                (string) $device->provider
            )
        ) === 'intelbras'
            && strtolower(
                trim(
                    (string) $device->device_type
                )
            ) === 'facial_reader';
    }

    private function deviceModelMatchesSnapshot(
        AccessDeviceRecord $device,
        AccessDeviceConfigurationSnapshotRecord $snapshot,
    ): bool {
        $deviceModel = preg_replace(
            '/\s+/u',
            ' ',
            trim(
                (string) $device->model
            )
        );

        $snapshotModel = preg_replace(
            '/\s+/u',
            ' ',
            trim(
                (string) $snapshot->device_model
            )
        );

        return is_string($deviceModel)
            && is_string($snapshotModel)
            && $deviceModel !== ''
            && $snapshotModel !== ''
            && strtoupper($deviceModel)
                === strtoupper($snapshotModel);
    }

    private function sameScope(
        object $left,
        object $right,
    ): bool {
        return (string) (
            $left->tenant_id
                ?? ''
        ) === (string) (
            $right->tenant_id
                ?? ''
        )
            && (string) (
                $left->organization_id
                    ?? ''
            ) === (string) (
                $right->organization_id
                    ?? ''
            );
    }

    private function enumValue(
        mixed $value
    ): string {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return trim(
            (string) $value
        );
    }

    private function validSha256(
        mixed $value
    ): bool {
        return is_string($value)
            && preg_match(
                '/^[a-f0-9]{64}$/D',
                $value
            ) === 1;
    }
}
