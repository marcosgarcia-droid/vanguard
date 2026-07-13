<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;

final class VisitorRecordPolicy
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
        return $user->can('ViewAny:VisitorRecord');
    }

    public function view(User $user, VisitorRecord $visitor): bool
    {
        return $user->can('View:VisitorRecord')
            && $this->canAccessRecord($user, $visitor);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:VisitorRecord')
            && app(TenantContext::class)->currentTenantIdForUser($user) !== null;
    }

    public function update(User $user, VisitorRecord $visitor): bool
    {
        return $user->can('Update:VisitorRecord')
            && $this->canAccessRecord($user, $visitor);
    }

    public function delete(User $user, VisitorRecord $visitor): bool
    {
        return $user->can('Delete:VisitorRecord')
            && $this->canAccessRecord($user, $visitor);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:VisitorRecord');
    }

    public function restore(User $user, VisitorRecord $visitor): bool
    {
        return $user->can('Restore:VisitorRecord')
            && $this->canAccessRecord($user, $visitor);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:VisitorRecord');
    }

    public function forceDelete(User $user, VisitorRecord $visitor): bool
    {
        return $user->can('ForceDelete:VisitorRecord')
            && $this->canAccessRecord($user, $visitor);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:VisitorRecord');
    }

    private function canAccessRecord(
        User $user,
        VisitorRecord $visitor
    ): bool {
        if (
            blank($visitor->tenant_id)
            || blank($visitor->organization_id)
        ) {
            return false;
        }

        $tenantContext = app(TenantContext::class);

        $hasTenantAccess = $tenantContext
            ->availableTenantsForUser($user)
            ->contains('id', $visitor->tenant_id);

        if (! $hasTenantAccess) {
            return false;
        }

        return $tenantContext->hasOrganizationAccess(
            $user,
            $visitor->organization_id
        );
    }
}
