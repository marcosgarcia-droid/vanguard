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
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorContactRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorDocumentRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

trait LogsVanguardActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('vanguard')
            ->logFillable()
            ->logOnlyDirty()
            ->logExcept($this->activityLogExcludedAttributes())
            ->setDescriptionForEvent(
                fn (string $eventName): string => $this->activityLogDescription(
                    $eventName
                )
            );
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $parent = $this->activityLogParentReference();

        if ($parent === null) {
            return;
        }

        $properties = $activity->properties;

        if ($properties instanceof Collection) {
            $properties = $properties->toArray();
        }

        if (! is_array($properties)) {
            $properties = [];
        }

        $activity->properties = array_merge($properties, [
            'vanguard_parent_type' => $parent['type'],
            'vanguard_parent_id' => (string) $parent['id'],
            'vanguard_parent_label' => $this->activityLogModelLabel(
                $parent['type']
            ),
        ]);
    }

    /**
     * Permite que um model defina diretamente seu registro pai.
     *
     * Atualmente, o vínculo dos registros filhos também é resolvido
     * centralmente pelo VanguardActivityLogParentResolver.
     *
     * @return array{type: class-string, id: mixed}|null
     */
    protected function activityLogParentReference(): ?array
    {
        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function activityLogExcludedAttributes(): array
    {
        return array_values(array_unique(array_merge([
            'password',
            'remember_token',
            'email_verified_at',
            'created_at',
            'updated_at',
            'deleted_at',
            'cnpj_normalized_data',
            'photo_path',
            'photo_disk',
        ], $this->activityLogSpecificExcludedAttributes())));
    }

    /**
     * @return array<int, string>
     */
    protected function activityLogSpecificExcludedAttributes(): array
    {
        return match (static::class) {
            EmployeeRecord::class => [
                'birth_date',
                'gender',
                'photo_uploaded_at',
            ],

            VisitorRecord::class => [
                'birth_date',
                'photo_uploaded_at',
            ],

            AccessDeviceRecord::class => [
                'credential_username',
                'credential_password',
                'current_configuration',
                'capabilities',
                'configuration_read_at',
                'configuration_read_status',
                'configuration_read_message',
            ],

            EmployeeDocumentRecord::class,
            PartnerDocumentRecord::class,
            VisitorDocumentRecord::class => [
                'number',
                'normalized_number',
            ],

            EmployeeContactRecord::class,
            PartnerContactRecord::class,
            OrganizationContactRecord::class,
            VisitorContactRecord::class => [
                'value',
                'normalized_value',
            ],

            EmployeeAddressRecord::class,
            PartnerAddressRecord::class,
            OrganizationAddressRecord::class => [
                'postal_code',
                'number',
                'complement',
                'latitude',
                'longitude',
            ],

            OrganizationMemberRecord::class => [
                'document_number',
                'representative_document_number',
                'metadata',
            ],

            OrganizationTaxRegimeRecord::class => [
                'tax_regime_details',
            ],

            OrganizationCnpjSyncRecord::class => [
                'request_payload',
                'response_payload',
                'normalized_payload',
                'response_hash',
            ],

            default => [],
        };
    }

    protected function activityLogDescription(string $eventName): string
    {
        $modelLabel = $this->activityLogModelLabel();

        return match ($eventName) {
            'created' => "{$modelLabel} criado",
            'updated' => "{$modelLabel} atualizado",
            'deleted' => "{$modelLabel} excluído",
            'restored' => "{$modelLabel} restaurado",
            default => "{$modelLabel}: {$eventName}",
        };
    }

    protected function activityLogModelLabel(?string $class = null): string
    {
        return match ($class ?? static::class) {
            User::class => 'Usuário',
            TenantRecord::class => 'Grupo empresarial',

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

            VisitorRecord::class => 'Visitante',
            VisitorDocumentRecord::class => 'Documento do visitante',
            VisitorContactRecord::class => 'Contato do visitante',
            VisitRecord::class => 'Visita',

            AccessDeviceRecord::class => 'Dispositivo de acesso',
            AccessEventOperationalDecisionRecord::class => 'Decisão operacional de acesso',

            ClassificationOptionRecord::class => 'Classificação',
            EmployeeWorkScheduleTemplateRecord::class => 'Jornada de trabalho',

            default => class_basename($class ?? static::class),
        };
    }
}
