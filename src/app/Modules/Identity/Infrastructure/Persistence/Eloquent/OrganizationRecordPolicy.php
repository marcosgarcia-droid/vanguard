<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class OrganizationRecordPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OrganizationRecord');
    }

    public function view(AuthUser $authUser, OrganizationRecord $organizationRecord): bool
    {
        return $authUser->can('View:OrganizationRecord');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OrganizationRecord');
    }

    public function update(AuthUser $authUser, OrganizationRecord $organizationRecord): bool
    {
        return $authUser->can('Update:OrganizationRecord');
    }

    public function delete(AuthUser $authUser, OrganizationRecord $organizationRecord): bool
    {
        return $authUser->can('Delete:OrganizationRecord');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:OrganizationRecord');
    }

    public function restore(AuthUser $authUser, OrganizationRecord $organizationRecord): bool
    {
        return $authUser->can('Restore:OrganizationRecord');
    }

    public function forceDelete(AuthUser $authUser, OrganizationRecord $organizationRecord): bool
    {
        return $authUser->can('ForceDelete:OrganizationRecord');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OrganizationRecord');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OrganizationRecord');
    }

    public function replicate(AuthUser $authUser, OrganizationRecord $organizationRecord): bool
    {
        return $authUser->can('Replicate:OrganizationRecord');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OrganizationRecord');
    }
}
