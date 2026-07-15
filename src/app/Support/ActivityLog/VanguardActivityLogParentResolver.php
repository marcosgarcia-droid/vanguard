<?php

namespace App\Support\ActivityLog;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeAddressRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeContactRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeDocumentRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleDayRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleRecord;
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
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorContactRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorDocumentRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

class VanguardActivityLogParentResolver
{
    /**
     * @return array{type: class-string, id: mixed, label: string}|null
     */
    public function resolve(Activity $activity): ?array
    {
        $parent = match ($activity->subject_type) {
            EmployeeDocumentRecord::class,
            EmployeeContactRecord::class,
            EmployeeAddressRecord::class,
            EmployeeWorkScheduleRecord::class => [
                'type' => EmployeeRecord::class,
                'id' => $this->foreignKeyValue(
                    $activity,
                    'employee_id'
                ),
            ],

            EmployeeWorkScheduleDayRecord::class => [
                'type' => EmployeeRecord::class,
                'id' => $this->employeeIdFromWorkScheduleDay(
                    $activity
                ),
            ],

            PartnerDocumentRecord::class,
            PartnerContactRecord::class,
            PartnerAddressRecord::class => [
                'type' => PartnerRecord::class,
                'id' => $this->foreignKeyValue(
                    $activity,
                    'partner_id'
                ),
            ],

            VisitorDocumentRecord::class,
            VisitorContactRecord::class,
            VisitRecord::class,
            AccessEventOperationalDecisionRecord::class => [
                'type' => VisitorRecord::class,
                'id' => $this->foreignKeyValue(
                    $activity,
                    'visitor_id'
                ),
            ],

            OrganizationAddressRecord::class,
            OrganizationContactRecord::class,
            OrganizationCnaeActivityRecord::class,
            OrganizationMemberRecord::class,
            OrganizationTaxRegimeRecord::class,
            OrganizationCnpjSyncRecord::class => [
                'type' => OrganizationRecord::class,
                'id' => $this->foreignKeyValue(
                    $activity,
                    'organization_id'
                ),
            ],

            default => null,
        };

        if ($parent === null || blank($parent['id'])) {
            return null;
        }

        return [
            'type' => $parent['type'],
            'id' => $parent['id'],
            'label' => $this->labelFor($parent['type']),
        ];
    }

    public function labelFor(string $class): string
    {
        return match ($class) {
            User::class => 'Usuário',
            TenantRecord::class => 'Grupo empresarial',
            OrganizationRecord::class => 'Organização',
            EmployeeRecord::class => 'Funcionário',
            PartnerRecord::class => 'Parceiro',
            VisitorRecord::class => 'Visitante',
            VisitRecord::class => 'Visita',
            default => class_basename($class),
        };
    }

    private function foreignKeyValue(
        Activity $activity,
        string $key
    ): mixed {
        $subject = $activity->subject;

        if (
            $subject instanceof Model
            && filled($subject->getAttribute($key))
        ) {
            return $subject->getAttribute($key);
        }

        return data_get(
            $activity->attribute_changes,
            "attributes.{$key}"
        ) ?? data_get(
            $activity->attribute_changes,
            "old.{$key}"
        );
    }

    private function employeeIdFromWorkScheduleDay(
        Activity $activity
    ): mixed {
        $subject = $activity->subject;

        $scheduleId = null;

        if ($subject instanceof EmployeeWorkScheduleDayRecord) {
            $scheduleId = $subject->getAttribute(
                'employee_work_schedule_id'
            );
        }

        $scheduleId ??= data_get(
            $activity->attribute_changes,
            'attributes.employee_work_schedule_id'
        ) ?? data_get(
            $activity->attribute_changes,
            'old.employee_work_schedule_id'
        );

        if (blank($scheduleId)) {
            return null;
        }

        return EmployeeWorkScheduleRecord::query()
            ->whereKey($scheduleId)
            ->value('employee_id');
    }
}
