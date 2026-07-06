<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User;

class ClassificationOptionRecordPolicy
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
        return $user->can('ViewAny:ClassificationOptionRecord');
    }

    public function view(User $user, ClassificationOptionRecord $classification): bool
    {
        return $user->can('View:ClassificationOptionRecord')
            && $this->canAccessClassification($user, $classification);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:ClassificationOptionRecord')
            && $this->hasAnyActiveTenant($user);
    }

    public function update(User $user, ClassificationOptionRecord $classification): bool
    {
        return $user->can('Update:ClassificationOptionRecord')
            && $this->canAccessClassification($user, $classification);
    }

    public function delete(User $user, ClassificationOptionRecord $classification): bool
    {
        return $user->can('Delete:ClassificationOptionRecord')
            && ! $classification->is_system
            && $this->canAccessClassification($user, $classification);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:ClassificationOptionRecord');
    }

    public function restore(User $user, ClassificationOptionRecord $classification): bool
    {
        return $user->can('Restore:ClassificationOptionRecord')
            && $this->canAccessClassification($user, $classification);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:ClassificationOptionRecord');
    }

    public function forceDelete(User $user, ClassificationOptionRecord $classification): bool
    {
        return $user->can('ForceDelete:ClassificationOptionRecord')
            && ! $classification->is_system
            && $this->canAccessClassification($user, $classification);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:ClassificationOptionRecord');
    }

    private function canAccessClassification(User $user, ClassificationOptionRecord $classification): bool
    {
        return $user->tenants()
            ->where('tenants.id', $classification->tenant_id)
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
