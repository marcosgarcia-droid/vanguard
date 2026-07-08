<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;

class PartnerRecordPolicy
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
        return $user->can('ViewAny:PartnerRecord');
    }

    public function view(User $user, PartnerRecord $partner): bool
    {
        return $user->can('View:PartnerRecord') && $this->canAccessPartner($user, $partner);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:PartnerRecord') && $this->hasAnyActiveTenant($user);
    }

    public function update(User $user, PartnerRecord $partner): bool
    {
        return $user->can('Update:PartnerRecord') && $this->canAccessPartner($user, $partner);
    }

    public function delete(User $user, PartnerRecord $partner): bool
    {
        return $user->can('Delete:PartnerRecord') && $this->canAccessPartner($user, $partner);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:PartnerRecord');
    }

    public function restore(User $user, PartnerRecord $partner): bool
    {
        return $user->can('Restore:PartnerRecord') && $this->canAccessPartner($user, $partner);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:PartnerRecord');
    }

    public function forceDelete(User $user, PartnerRecord $partner): bool
    {
        return $user->can('ForceDelete:PartnerRecord') && $this->canAccessPartner($user, $partner);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:PartnerRecord');
    }

    private function canAccessPartner(User $user, PartnerRecord $partner): bool
    {
        $hasTenantAccess = $user->tenants()
            ->where('tenants.id', $partner->tenant_id)
            ->wherePivot('is_active', true)
            ->exists();

        if (! $hasTenantAccess) {
            return false;
        }

        return app(TenantContext::class)->hasOrganizationAccess($user, $partner->organization_id);
    }

    private function hasAnyActiveTenant(User $user): bool
    {
        return $user->tenants()
            ->wherePivot('is_active', true)
            ->exists();
    }
}
