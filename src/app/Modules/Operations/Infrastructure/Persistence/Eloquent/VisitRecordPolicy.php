<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;

final class VisitRecordPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole(
            config('filament-shield.super_admin.name', 'super_admin')
        )
            ? true
            : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:VisitRecord');
    }

    public function view(User $user, VisitRecord $visit): bool
    {
        return $user->can('View:VisitRecord')
            && $this->canAccessRecord($user, $visit);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:VisitRecord')
            && app(TenantContext::class)->currentTenantIdForUser($user) !== null;
    }

    public function update(User $user, VisitRecord $visit): bool
    {
        return $user->can('Update:VisitRecord')
            && $this->canAccessRecord($user, $visit);
    }

    public function authorizeVehicleEntry(
        User $user,
        VisitRecord $visit
    ): bool {
        return $user->can(
            'AuthorizeVehicleEntry:VisitRecord'
        )
            && $this->canAccessRecord($user, $visit);
    }

    public function delete(User $user, VisitRecord $visit): bool
    {
        return $user->can('Delete:VisitRecord')
            && $this->canAccessRecord($user, $visit);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:VisitRecord');
    }

    public function restore(User $user, VisitRecord $visit): bool
    {
        return $user->can('Restore:VisitRecord')
            && $this->canAccessRecord($user, $visit);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:VisitRecord');
    }

    public function forceDelete(User $user, VisitRecord $visit): bool
    {
        return $user->can('ForceDelete:VisitRecord')
            && $this->canAccessRecord($user, $visit);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:VisitRecord');
    }

    private function canAccessRecord(
        User $user,
        VisitRecord $visit
    ): bool {
        if (
            blank($visit->tenant_id)
            || blank($visit->organization_id)
        ) {
            return false;
        }

        $tenantContext = app(TenantContext::class);

        $hasTenantAccess = $tenantContext
            ->availableTenantsForUser($user)
            ->contains('id', $visit->tenant_id);

        if (! $hasTenantAccess) {
            return false;
        }

        return $tenantContext->hasOrganizationAccess(
            $user,
            $visit->organization_id
        );
    }
}
