<?php

namespace App\Support\ActivityLog;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\ClassificationOptionRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeAddressRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeContactRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeDocumentRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleDayRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleTemplateRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationAddressRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationCnaeActivityRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationCnpjSyncRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationContactRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationMemberRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationTaxRegimeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerAddressRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerContactRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerDocumentRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationSource;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionSource;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalExecutionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class VanguardActivityLogPresenter
{
    public static function eventLabel(?string $event): string
    {
        return match ($event) {
            'created' => 'Criado',
            'updated' => 'Atualizado',
            'deleted' => 'Excluído',
            'restored' => 'Restaurado',
            'configuration_read' => 'Leitura de configurações',
            'access_event_flow_reprocessed' => 'Reprocessamento do fluxo',
            'access_event_manually_associated' => 'Associação manual',
            default => $event ? Str::headline($event) : '-',
        };
    }

    public static function subjectLabel(Activity $activity): string
    {
        $subject = $activity->subject;

        if ($subject instanceof AccessDeviceRecord) {
            $displayName = trim(
                (string) $subject->display_name
            );

            if ($displayName !== '') {
                return 'Dispositivo de acesso — '
                    .$displayName;
            }
        }

        if (
            $subject instanceof AccessEventOperationalDecisionRecord
        ) {
            return 'Decisão operacional — versão '
                .$subject->version
                .' — '
                .(
                    $subject->decision?->label()
                    ?: '-'
                );
        }

        if (
            $subject instanceof AccessEventOperationalExecutionRecord
        ) {
            return 'Tentativa operacional — tentativa '
                .$subject->attempt_number
                .' — '
                .(
                    $subject->status?->label()
                    ?: '-'
                );
        }

        if ($subject instanceof AccessEventRecord) {
            $display = collect([
                $subject->occurred_at
                    ?->format('d/m/Y H:i:s'),
                trim(
                    (string) $subject
                        ->external_event_id
                ),
            ])
                ->filter()
                ->implode(' — ');

            if ($display !== '') {
                return 'Evento de acesso — '
                    .$display;
            }
        }

        $label = self::modelLabel(
            $activity->subject_type
        );

        if (blank($activity->subject_id)) {
            return $label;
        }

        return "{$label} #{$activity->subject_id}";
    }

    public static function modelLabel(?string $class): string
    {
        return match ($class) {
            User::class => 'Usuário',
            TenantRecord::class => 'Grupo empresarial',
            AccessDeviceRecord::class => 'Dispositivo de acesso',
            AccessEventRecord::class => 'Evento de acesso',
            AccessEventOperationalDecisionRecord::class => 'Decisão operacional de acesso',
            AccessEventOperationalExecutionRecord::class => 'Tentativa de execução operacional',

            OrganizationRecord::class => 'Organização',
            OrganizationAddressRecord::class => 'Endereço da organização',
            OrganizationContactRecord::class => 'Contato da organização',
            OrganizationCnaeActivityRecord::class => 'CNAE da organização',
            OrganizationMemberRecord::class => 'Sócio da organização',
            OrganizationTaxRegimeRecord::class => 'Regime tributário da organização',
            OrganizationCnpjSyncRecord::class => 'Sincronização CNPJ',

            EmployeeRecord::class => 'Funcionário',
            EmployeeDocumentRecord::class => 'Documento do funcionário',
            EmployeeContactRecord::class => 'Contato do funcionário',
            EmployeeAddressRecord::class => 'Endereço do funcionário',
            EmployeeWorkScheduleRecord::class => 'Jornada do funcionário',
            EmployeeWorkScheduleDayRecord::class => 'Dia da jornada do funcionário',

            PartnerRecord::class => 'Parceiro',
            PartnerDocumentRecord::class => 'Documento do parceiro',
            PartnerContactRecord::class => 'Contato do parceiro',
            PartnerAddressRecord::class => 'Endereço do parceiro',

            ClassificationOptionRecord::class => 'Classificação',
            EmployeeWorkScheduleTemplateRecord::class => 'Jornada de trabalho',

            default => $class ? class_basename($class) : 'Registro',
        };
    }

    public static function fieldLabel(string $field): string
    {
        return match ($field) {
            'id' => 'ID',
            'tenant_id' => 'Grupo empresarial',
            'organization_id' => 'Organização',
            'employee_id' => 'Funcionário',
            'partner_id' => 'Parceiro',
            'user_id' => 'Usuário',
            'manager_employee_id' => 'Gestor responsável',

            'type' => 'Tipo',
            'label' => 'Rótulo',
            'name' => 'Nome',
            'full_name' => 'Nome completo',
            'preferred_name' => 'Nome preferencial',
            'employee_code' => 'Matrícula',
            'code' => 'Código',
            'description' => 'Descrição',
            'notes' => 'Observações',
            'status' => 'Status',
            'access_event_id' => 'Evento de acesso',
            'operational_decision_id' => 'Decisão operacional',
            'visitor_id' => 'Visitante',
            'visit_id' => 'Visita',
            'operator_user_id' => 'Operador',
            'version' => 'Versão',
            'decision' => 'Decisão',
            'reason_code' => 'Código do motivo',
            'reason_message' => 'Motivo',
            'automatic_execution_enabled' => 'Execução automática habilitada',
            'attempt_number' => 'Número da tentativa',
            'automatic_execution_allowed' => 'Execução automática permitida',
            'visit_status_before' => 'Status anterior da visita',
            'visit_status_after' => 'Status posterior da visita',
            'attempted_at' => 'Tentativa iniciada em',
            'completed_at' => 'Tentativa concluída em',

            'is_primary' => 'Principal',
            'is_active' => 'Ativo',
            'is_verified' => 'Verificado',
            'source' => 'Origem',
            'duration_ms' => 'Duração',
            'message' => 'Mensagem',
            'warnings' => 'Avisos',

            'department' => 'Departamento',
            'position' => 'Cargo',
            'employment_type' => 'Tipo de vínculo',
            'hired_at' => 'Data de admissão',
            'terminated_at' => 'Data de desligamento',

            'weekday' => 'Dia da semana',
            'sequence' => 'Sequência',
            'is_working_day' => 'Dia trabalhado',
            'work_starts_at' => 'Início da jornada',
            'work_ends_at' => 'Fim da jornada',
            'ends_next_day' => 'Termina no dia seguinte',
            'break_starts_at' => 'Início do intervalo',
            'break_ends_at' => 'no dia seguinte',
            'break_starts_at' => 'Início do intervalo',
            'break_ends_at' => 'Fim do intervalo',

            default => Str::of($field)->replace('_', ' ')->headline()->toString(),
        };
    }

    /**
     * @return array<int, array{
     *     label: string,
     *     value: string
     * }>
     */
    public static function operationDetails(
        Activity $activity
    ): array {
        if (
            $activity->subject_type
            === AccessEventOperationalDecisionRecord::class
        ) {
            return self::accessEventDecisionDetails(
                $activity
            );
        }

        if (
            $activity->subject_type
            === AccessEventOperationalExecutionRecord::class
        ) {
            return self::accessEventExecutionDetails(
                $activity
            );
        }

        if (
            $activity->event
            === 'access_event_flow_reprocessed'
        ) {
            return self::accessEventFlowReprocessDetails(
                $activity
            );
        }

        if (
            $activity->event
            === 'access_event_manually_associated'
        ) {
            return self::accessEventManualAssociationDetails(
                $activity
            );
        }

        if (
            $activity->event
            !== 'configuration_read'
        ) {
            return [];
        }

        $statusValue = data_get(
            $activity->properties,
            'status'
        );

        $sourceValue = data_get(
            $activity->properties,
            'source'
        );

        $durationMs = data_get(
            $activity->properties,
            'duration_ms'
        );

        $message = trim(
            (string) data_get(
                $activity->properties,
                'message',
                ''
            )
        );

        $status = AccessDeviceConfigurationReadStatus::tryFrom(
            (string) $statusValue
        );

        $source = AccessDeviceConfigurationSource::tryFrom(
            (string) $sourceValue
        );

        $details = [];

        if ($status !== null) {
            $details[] = [
                'label' => 'Resultado',
                'value' => $status->label(),
            ];
        } elseif (filled($statusValue)) {
            $details[] = [
                'label' => 'Resultado',
                'value' => self::value(
                    $statusValue
                ),
            ];
        }

        if ($source !== null) {
            $details[] = [
                'label' => 'Origem',
                'value' => $source->label(),
            ];
        } elseif (filled($sourceValue)) {
            $details[] = [
                'label' => 'Origem',
                'value' => self::value(
                    $sourceValue
                ),
            ];
        }

        if (is_numeric($durationMs)) {
            $details[] = [
                'label' => 'Duração',
                'value' => number_format(
                    (float) $durationMs,
                    0,
                    ',',
                    '.'
                ).' ms',
            ];
        }

        if ($message !== '') {
            $details[] = [
                'label' => 'Mensagem',
                'value' => $message,
            ];
        }

        return $details;
    }

    /**
     * @return array<int, array{
     *     label: string,
     *     value: string
     * }>
     */
    private static function accessEventDecisionDetails(
        Activity $activity
    ): array {
        $version = self::activityAttribute(
            $activity,
            'version'
        );

        $decisionValue = self::activityAttribute(
            $activity,
            'decision'
        );

        $reasonMessage = trim(
            (string) self::activityAttribute(
                $activity,
                'reason_message'
            )
        );

        $reasonCode = trim(
            (string) self::activityAttribute(
                $activity,
                'reason_code'
            )
        );

        $automaticExecution =
            self::booleanState(
                self::activityAttribute(
                    $activity,
                    'automatic_execution_enabled'
                )
            );

        $decision =
            AccessEventOperationalDecision::tryFrom(
                (string) $decisionValue
            );

        $details = [];

        if (is_numeric($version)) {
            $details[] = [
                'label' => 'Versão',
                'value' => (string) $version,
            ];
        }

        if ($decision !== null) {
            $details[] = [
                'label' => 'Decisão',
                'value' => $decision->label(),
            ];
        }

        if (
            $reasonMessage !== ''
            || $reasonCode !== ''
        ) {
            $details[] = [
                'label' => 'Motivo',
                'value' => $reasonMessage !== ''
                    ? $reasonMessage
                    : Str::of($reasonCode)
                        ->replace('_', ' ')
                        ->headline()
                        ->toString(),
            ];
        }

        if ($automaticExecution !== null) {
            $details[] = [
                'label' => 'Execução automática habilitada',
                'value' => $automaticExecution
                    ? 'Sim'
                    : 'Não',
            ];
        }

        return $details;
    }

    /**
     * @return array<int, array{
     *     label: string,
     *     value: string
     * }>
     */
    private static function accessEventExecutionDetails(
        Activity $activity
    ): array {
        $attemptNumber = self::activityAttribute(
            $activity,
            'attempt_number'
        );

        $sourceValue = self::activityAttribute(
            $activity,
            'source'
        );

        $statusValue = self::activityAttribute(
            $activity,
            'status'
        );

        $reasonMessage = trim(
            (string) self::activityAttribute(
                $activity,
                'reason_message'
            )
        );

        $reasonCode = trim(
            (string) self::activityAttribute(
                $activity,
                'reason_code'
            )
        );

        $automaticExecution =
            self::booleanState(
                self::activityAttribute(
                    $activity,
                    'automatic_execution_allowed'
                )
            );

        $visitStatusBeforeValue =
            self::activityAttribute(
                $activity,
                'visit_status_before'
            );

        $visitStatusAfterValue =
            self::activityAttribute(
                $activity,
                'visit_status_after'
            );

        $source =
            AccessEventOperationalExecutionSource::tryFrom(
                (string) $sourceValue
            );

        $status =
            AccessEventOperationalExecutionStatus::tryFrom(
                (string) $statusValue
            );

        $visitStatusBefore =
            VisitStatus::tryFrom(
                (string) $visitStatusBeforeValue
            );

        $visitStatusAfter =
            VisitStatus::tryFrom(
                (string) $visitStatusAfterValue
            );

        $details = [];

        if (is_numeric($attemptNumber)) {
            $details[] = [
                'label' => 'Tentativa',
                'value' => (string) $attemptNumber,
            ];
        }

        if ($source !== null) {
            $details[] = [
                'label' => 'Origem',
                'value' => $source->label(),
            ];
        }

        if ($status !== null) {
            $details[] = [
                'label' => 'Status',
                'value' => $status->label(),
            ];
        }

        if (
            $reasonMessage !== ''
            || $reasonCode !== ''
        ) {
            $details[] = [
                'label' => 'Motivo',
                'value' => $reasonMessage !== ''
                    ? $reasonMessage
                    : Str::of($reasonCode)
                        ->replace('_', ' ')
                        ->headline()
                        ->toString(),
            ];
        }

        if ($automaticExecution !== null) {
            $details[] = [
                'label' => 'Execução automática permitida',
                'value' => $automaticExecution
                    ? 'Sim'
                    : 'Não',
            ];
        }

        if ($visitStatusBefore !== null) {
            $details[] = [
                'label' => 'Status anterior da visita',
                'value' => $visitStatusBefore->label(),
            ];
        }

        if ($visitStatusAfter !== null) {
            $details[] = [
                'label' => 'Status posterior da visita',
                'value' => $visitStatusAfter->label(),
            ];
        }

        return $details;
    }

    private static function activityAttribute(
        Activity $activity,
        string $field
    ): mixed {
        $value = data_get(
            $activity->attribute_changes,
            "attributes.{$field}"
        );

        $value ??= data_get(
            $activity->attribute_changes,
            "old.{$field}"
        );

        if ($value !== null) {
            return $value instanceof \BackedEnum
                ? $value->value
                : $value;
        }

        $subject = $activity->subject;

        if (! $subject instanceof Model) {
            return null;
        }

        $value = $subject->getAttribute(
            $field
        );

        return $value instanceof \BackedEnum
            ? $value->value
            : $value;
    }

    private static function booleanState(
        mixed $value
    ): ?bool {
        if (is_bool($value)) {
            return $value;
        }

        if (
            in_array(
                $value,
                [
                    1,
                    '1',
                    'true',
                ],
                true
            )
        ) {
            return true;
        }

        if (
            in_array(
                $value,
                [
                    0,
                    '0',
                    'false',
                ],
                true
            )
        ) {
            return false;
        }

        return null;
    }

    /**
     * @return array<int, array{
     *     label: string,
     *     value: string
     * }>
     */
    private static function accessEventFlowReprocessDetails(
        Activity $activity
    ): array {
        $statusValue = data_get(
            $activity->properties,
            'status'
        );

        $processingValue = data_get(
            $activity->properties,
            'processing_status'
        );

        $decisionValue = data_get(
            $activity->properties,
            'decision'
        );

        $executionValue = data_get(
            $activity->properties,
            'execution_status'
        );

        $decisionVersion = data_get(
            $activity->properties,
            'decision_version'
        );

        $reasonCode = trim(
            (string) data_get(
                $activity->properties,
                'execution_reason_code',
                ''
            )
        );

        $allDuplicates = data_get(
            $activity->properties,
            'all_duplicates'
        );

        $message = trim(
            (string) data_get(
                $activity->properties,
                'message',
                ''
            )
        );

        $processing =
            AccessEventStatus::tryFrom(
                (string) $processingValue
            );

        $decision =
            AccessEventOperationalDecision::tryFrom(
                (string) $decisionValue
            );

        $execution =
            AccessEventOperationalExecutionStatus::tryFrom(
                (string) $executionValue
            );

        $details = [
            [
                'label' => 'Resultado',
                'value' => $statusValue === 'failed'
                    ? 'Falha'
                    : 'Concluído',
            ],
        ];

        if ($processing !== null) {
            $details[] = [
                'label' => 'Processamento',
                'value' => $processing->label(),
            ];
        }

        if ($decision !== null) {
            $details[] = [
                'label' => 'Decisão',
                'value' => $decision->label(),
            ];
        }

        if (is_numeric($decisionVersion)) {
            $details[] = [
                'label' => 'Versão da decisão',
                'value' => (string) $decisionVersion,
            ];
        }

        if ($execution !== null) {
            $details[] = [
                'label' => 'Tentativa',
                'value' => $execution->label(),
            ];
        }

        if ($reasonCode !== '') {
            $details[] = [
                'label' => 'Código do motivo',
                'value' => $reasonCode,
            ];
        }

        if (is_bool($allDuplicates)) {
            $details[] = [
                'label' => 'Sem novas alterações',
                'value' => $allDuplicates
                    ? 'Sim'
                    : 'Não',
            ];
        }

        if ($message !== '') {
            $details[] = [
                'label' => 'Mensagem',
                'value' => $message,
            ];
        }

        return $details;
    }

    /**
     * @return array<int, array{
     *     label: string,
     *     value: string
     * }>
     */
    private static function accessEventManualAssociationDetails(
        Activity $activity
    ): array {
        $statusValue = trim(
            (string) data_get(
                $activity->properties,
                'status',
                ''
            )
        );

        $visitorName = trim(
            (string) data_get(
                $activity->properties,
                'visitor_name',
                ''
            )
        );

        $visitReference = trim(
            (string) data_get(
                $activity->properties,
                'visit_reference',
                ''
            )
        );

        $resultingStatusValue = data_get(
            $activity->properties,
            'resulting_status'
        );

        $reason = trim(
            (string) data_get(
                $activity->properties,
                'reason',
                ''
            )
        );

        $message = trim(
            (string) data_get(
                $activity->properties,
                'message',
                ''
            )
        );

        $duplicate = self::booleanState(
            data_get(
                $activity->properties,
                'duplicate'
            )
        );

        $resultingStatus =
            AccessEventStatus::tryFrom(
                (string) $resultingStatusValue
            );

        $resultLabel = match (
            $statusValue
        ) {
            'success' => 'Concluído',
            'failed' => 'Falha',

            default => $statusValue !== ''
                ? Str::of($statusValue)
                    ->replace('_', ' ')
                    ->headline()
                    ->toString()
                : 'Não informado',
        };

        $details = [
            [
                'label' => 'Resultado',
                'value' => $resultLabel,
            ],
        ];

        if ($visitorName !== '') {
            $details[] = [
                'label' => 'Visitante',
                'value' => $visitorName,
            ];
        }

        if ($visitReference !== '') {
            $details[] = [
                'label' => 'Visita',
                'value' => $visitReference,
            ];
        } elseif ($statusValue === 'success') {
            $details[] = [
                'label' => 'Visita',
                'value' => 'Não associada',
            ];
        }

        if ($resultingStatus !== null) {
            $details[] = [
                'label' => 'Situação final',
                'value' => $resultingStatus->label(),
            ];
        }

        if ($reason !== '') {
            $details[] = [
                'label' => 'Justificativa',
                'value' => $reason,
            ];
        }

        if ($duplicate === true) {
            $details[] = [
                'label' => 'Solicitação já registrada',
                'value' => 'Sim',
            ];
        }

        if ($message !== '') {
            $details[] = [
                'label' => 'Mensagem',
                'value' => $message,
            ];
        }

        return $details;
    }

    public static function value(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Não informado';
        }

        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '-';
        }

        return (string) $value;
    }
}
