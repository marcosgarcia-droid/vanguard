<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User;

final class EmployeeWorkScheduleTemplateRecordPolicy
{
    public function before(User $user): ?bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:EmployeeWorkScheduleTemplateRecord');
    }

    public function view(User $user, EmployeeWorkScheduleTemplateRecord $template): bool
    {
        return $user->can('View:EmployeeWorkScheduleTemplateRecord')
            && $this->canAccessTemplate($user, $template);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:EmployeeWorkScheduleTemplateRecord')
            && $this->hasAnyActiveTenant($user);
    }

    public function update(User $user, EmployeeWorkScheduleTemplateRecord $template): bool
    {
        return $user->can('Update:EmployeeWorkScheduleTemplateRecord')
            && $this->canAccessTemplate($user, $template);
    }

    public function delete(User $user, EmployeeWorkScheduleTemplateRecord $template): bool
    {
        return $user->can('Delete:EmployeeWorkScheduleTemplateRecord')
            && ! $template->is_system
            && $this->canAccessTemplate($user, $template);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:EmployeeWorkScheduleTemplateRecord');
    }

    public function restore(User $user, EmployeeWorkScheduleTemplateRecord $template): bool
    {
        return $user->can('Restore:EmployeeWorkScheduleTemplateRecord')
            && $this->canAccessTemplate($user, $template);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:EmployeeWorkScheduleTemplateRecord');
    }

    public function forceDelete(User $user, EmployeeWorkScheduleTemplateRecord $template): bool
    {
        return $user->can('ForceDelete:EmployeeWorkScheduleTemplateRecord')
            && ! $template->is_system
            && $this->canAccessTemplate($user, $template);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:EmployeeWorkScheduleTemplateRecord');
    }

    private function canAccessTemplate(User $user, EmployeeWorkScheduleTemplateRecord $template): bool
    {
        return $user->tenants()
            ->where('tenants.id', $template->tenant_id)
            ->wherePivot('is_active', true)
            ->exists();
    }

    private function hasAnyActiveTenant(User $user): bool
    {
        return $user->tenants()
            ->wherePivot('is_active', true)
            ->exists();
    }
}
