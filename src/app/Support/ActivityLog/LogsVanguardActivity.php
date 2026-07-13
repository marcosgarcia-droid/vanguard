<?php

namespace App\Support\ActivityLog;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\ClassificationOptionRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleTemplateRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
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
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    /**
     * @return array<int, string>
     */
    protected function activityLogExcludedAttributes(): array
    {
        return [
            'password',
            'remember_token',
            'email_verified_at',
            'created_at',
            'updated_at',
            'deleted_at',
            'cnpj_normalized_data',
            'photo_path',
            'photo_disk',
        ];
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

    protected function activityLogModelLabel(): string
    {
        return match (static::class) {
            User::class => 'Usuário',
            TenantRecord::class => 'Grupo empresarial',
            OrganizationRecord::class => 'Organização',
            EmployeeRecord::class => 'Funcionário',
            PartnerRecord::class => 'Parceiro',
            ClassificationOptionRecord::class => 'Classificação',
            EmployeeWorkScheduleTemplateRecord::class => 'Jornada de trabalho',
            default => class_basename(static::class),
        };
    }
}
