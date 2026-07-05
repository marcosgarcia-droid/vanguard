<?php

namespace App\Modules\Identity\Application\Tenancy;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Illuminate\Database\Eloquent\Builder;

final class TenantContext
{
    public function currentTenantForUser(?User $user): ?TenantRecord
    {
        if ($user === null) {
            return null;
        }

        return $user->tenants()
            ->wherePivot('is_active', true)
            ->orderBy('tenants.name')
            ->first();
    }

    public function currentTenantIdForUser(?User $user): ?string
    {
        return $this->currentTenantForUser($user)?->id;
    }

    public function applyOrganizationScope(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('super_admin')) {
            return $query;
        }

        $tenantIds = $user->tenants()
            ->wherePivot('is_active', true)
            ->pluck('tenants.id')
            ->all();

        if ($tenantIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('tenant_id', $tenantIds);
    }
}
