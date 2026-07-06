<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User;

class TenantRecordPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if (! $user->hasRole(config('filament-shield.super_admin.name', 'super_admin'))) {
            return null;
        }

        if (in_array($ability, ['delete', 'forceDelete'], true)) {
            return null;
        }

        return true;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, TenantRecord $tenantRecord): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, TenantRecord $tenantRecord): bool
    {
        return false;
    }

    public function delete(User $user, TenantRecord $tenantRecord): bool
    {
        return $user->hasRole(config('filament-shield.super_admin.name', 'super_admin'))
            && $tenantRecord->organizations()->doesntExist();
    }

    public function forceDelete(User $user, TenantRecord $tenantRecord): bool
    {
        return false;
    }
}
