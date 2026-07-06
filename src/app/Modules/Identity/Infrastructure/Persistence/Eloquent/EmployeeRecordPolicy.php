<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;

class EmployeeRecordPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole(config('filament-shield.super_admin.name', 'super_admin'))
            ? true
            : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:EmployeeRecord');
    }

    public function view(User $user, EmployeeRecord $employeeRecord): bool
    {
        return $user->can('View:EmployeeRecord')
            && $this->belongsToActiveUserTenant($user, $employeeRecord);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:EmployeeRecord')
            && app(TenantContext::class)->currentTenantIdForUser($user) !== null;
    }

    public function update(User $user, EmployeeRecord $employeeRecord): bool
    {
        return $user->can('Update:EmployeeRecord')
            && $this->belongsToActiveUserTenant($user, $employeeRecord);
    }

    public function delete(User $user, EmployeeRecord $employeeRecord): bool
    {
        return $user->can('Delete:EmployeeRecord')
            && $this->belongsToActiveUserTenant($user, $employeeRecord);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:EmployeeRecord');
    }

    public function restore(User $user, EmployeeRecord $employeeRecord): bool
    {
        return $user->can('Restore:EmployeeRecord')
            && $this->belongsToActiveUserTenant($user, $employeeRecord);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:EmployeeRecord');
    }

    public function forceDelete(User $user, EmployeeRecord $employeeRecord): bool
    {
        return $user->can('ForceDelete:EmployeeRecord')
            && $this->belongsToActiveUserTenant($user, $employeeRecord);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:EmployeeRecord');
    }

    private function belongsToActiveUserTenant(User $user, EmployeeRecord $employeeRecord): bool
    {
        if (blank($employeeRecord->tenant_id)) {
            return false;
        }

        return $user->tenants()
            ->wherePivot('is_active', true)
            ->where('tenants.id', $employeeRecord->tenant_id)
            ->exists();
    }
}
