<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;

class OrganizationRecordPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole(config('filament-shield.super_admin.name', 'super_admin'))
            ? true
            : null;
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'ViewAny:OrganizationRecord');
    }

    public function view(User $user, OrganizationRecord $organizationRecord): bool
    {
        return $this->can($user, 'View:OrganizationRecord')
            && $this->belongsToActiveUserTenant($user, $organizationRecord);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'Create:OrganizationRecord')
            && app(TenantContext::class)->currentTenantIdForUser($user) !== null;
    }

    public function update(User $user, OrganizationRecord $organizationRecord): bool
    {
        return $this->can($user, 'Update:OrganizationRecord')
            && $this->belongsToActiveUserTenant($user, $organizationRecord);
    }

    public function delete(User $user, OrganizationRecord $organizationRecord): bool
    {
        return $this->can($user, 'Delete:OrganizationRecord')
            && $this->belongsToActiveUserTenant($user, $organizationRecord);
    }

    public function deleteAny(User $user): bool
    {
        return $this->can($user, 'DeleteAny:OrganizationRecord');
    }

    public function restore(User $user, OrganizationRecord $organizationRecord): bool
    {
        return $this->can($user, 'Restore:OrganizationRecord')
            && $this->belongsToActiveUserTenant($user, $organizationRecord);
    }

    public function restoreAny(User $user): bool
    {
        return $this->can($user, 'RestoreAny:OrganizationRecord');
    }

    public function forceDelete(User $user, OrganizationRecord $organizationRecord): bool
    {
        return $this->can($user, 'ForceDelete:OrganizationRecord')
            && $this->belongsToActiveUserTenant($user, $organizationRecord);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->can($user, 'ForceDeleteAny:OrganizationRecord');
    }

    public function replicate(User $user, OrganizationRecord $organizationRecord): bool
    {
        return $this->can($user, 'Replicate:OrganizationRecord')
            && $this->belongsToActiveUserTenant($user, $organizationRecord);
    }

    public function reorder(User $user): bool
    {
        return $this->can($user, 'Reorder:OrganizationRecord');
    }

    private function can(User $user, string $permission): bool
    {
        return $user->can($permission);
    }

    private function belongsToActiveUserTenant(User $user, OrganizationRecord $organizationRecord): bool
    {
        if (blank($organizationRecord->tenant_id) || blank($organizationRecord->id)) {
            return false;
        }

        $hasTenantAccess = $user->tenants()
            ->wherePivot('is_active', true)
            ->where('tenants.id', $organizationRecord->tenant_id)
            ->exists();

        if (! $hasTenantAccess) {
            return false;
        }

        return app(TenantContext::class)->hasOrganizationAccess($user, (string) $organizationRecord->id);
    }
}
