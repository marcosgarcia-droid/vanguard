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
            default => $event ? Str::headline($event) : '-',
        };
    }

    public static function subjectLabel(Activity $activity): string
    {
        $label = self::modelLabel($activity->subject_type);

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

            'is_primary' => 'Principal',
            'is_active' => 'Ativo',
            'is_verified' => 'Verificado',
            'source' => 'Origem',

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
