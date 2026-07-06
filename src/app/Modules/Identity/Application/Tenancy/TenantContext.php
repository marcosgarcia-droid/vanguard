<?php

namespace App\Modules\Identity\Application\Tenancy;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class TenantContext
{
    private const SESSION_KEY = 'vanguard.current_tenant_id';

    public function currentTenantForUser(?User $user): ?TenantRecord
    {
        if ($user === null) {
            return null;
        }

        $selectedTenant = $this->selectedTenantForUser($user);

        if ($selectedTenant instanceof TenantRecord) {
            return $selectedTenant;
        }

        if ($this->isSuperAdmin($user)) {
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

    public function selectedTenantId(): ?string
    {
        $tenantId = session()->get(self::SESSION_KEY);

        return filled($tenantId) ? (string) $tenantId : null;
    }

    public function selectTenantForUser(?User $user, TenantRecord $tenant): bool
    {
        if (! $this->canSelectTenant($user, $tenant)) {
            return false;
        }

        session()->put(self::SESSION_KEY, $tenant->id);

        return true;
    }

    public function clearSelectedTenant(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function canSelectTenant(?User $user, TenantRecord $tenant): bool
    {
        if ($user === null || $tenant->status !== 'active') {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $user->tenants()
            ->wherePivot('is_active', true)
            ->where('tenants.id', $tenant->id)
            ->exists();
    }

    /**
     * @return Collection<int, TenantRecord>
     */
    public function availableTenantsForUser(?User $user): Collection
    {
        if ($user === null) {
            return TenantRecord::query()
                ->whereRaw('1 = 0')
                ->get();
        }

        if ($this->isSuperAdmin($user)) {
            return TenantRecord::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get();
        }

        return $user->tenants()
            ->wherePivot('is_active', true)
            ->where('tenants.status', 'active')
            ->orderBy('tenants.name')
            ->get();
    }

    public function applyOrganizationScope(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        $selectedTenant = $this->selectedTenantForUser($user);

        if ($selectedTenant instanceof TenantRecord) {
            return $query->where('tenant_id', $selectedTenant->id);
        }

        if ($this->isSuperAdmin($user)) {
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

    private function selectedTenantForUser(User $user): ?TenantRecord
    {
        $tenantId = $this->selectedTenantId();

        if ($tenantId === null) {
            return null;
        }

        $tenant = TenantRecord::query()
            ->where('status', 'active')
            ->find($tenantId);

        if (! $tenant instanceof TenantRecord) {
            $this->clearSelectedTenant();

            return null;
        }

        if (! $this->canSelectTenant($user, $tenant)) {
            $this->clearSelectedTenant();

            return null;
        }

        return $tenant;
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->hasRole(config('filament-shield.super_admin.name', 'super_admin'));
    }
}
