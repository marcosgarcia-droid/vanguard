<?php

namespace App\Modules\Identity\Application\Tenancy;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class TenantContext
{
    private const SESSION_KEY = 'vanguard.current_tenant_id';

    public function initializeForUser(?User $user): void
    {
        if ($user === null) {
            $this->clearSelectedTenant();

            return;
        }

        if ($this->isSuperAdmin($user)) {
            $this->clearSelectedTenant();

            return;
        }

        if ($this->selectedTenantForUser($user) instanceof TenantRecord) {
            return;
        }

        $tenant = $this->availableTenantsForUser($user)->first();

        if ($tenant instanceof TenantRecord) {
            session()->put(self::SESSION_KEY, $tenant->id);

            return;
        }

        $this->clearSelectedTenant();
    }

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

        return $this->availableTenantsForUser($user)->first();
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

        return in_array($tenant->id, $this->activeTenantIdsForUser($user), true);
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

        $tenantIds = $this->activeTenantIdsForUser($user);

        if ($tenantIds === []) {
            return TenantRecord::query()
                ->whereRaw('1 = 0')
                ->get();
        }

        return TenantRecord::query()
            ->where('status', 'active')
            ->whereIn('id', $tenantIds)
            ->orderBy('name')
            ->get();
    }

    public function applyTenantScope(Builder $query, ?User $user, string $tenantColumn = 'tenant_id'): Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        $currentTenant = $this->currentTenantForUser($user);

        if ($currentTenant instanceof TenantRecord) {
            return $query->where($tenantColumn, $currentTenant->id);
        }

        if ($this->isSuperAdmin($user)) {
            return $query;
        }

        $tenantIds = $this->availableTenantsForUser($user)
            ->pluck('id')
            ->all();

        if ($tenantIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($tenantColumn, $tenantIds);
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

        $tenantIds = $this->activeTenantIdsForUser($user);

        if ($tenantIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('tenant_id', $tenantIds);
    }

    /**
     * @return array<int, string>
     */
    public function allowedOrganizationIdsForUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        if ($this->isSuperAdmin($user)) {
            return [];
        }

        return $user->organizations()
            ->wherePivot('is_active', true)
            ->pluck('organizations.id')
            ->all();
    }

    public function hasOrganizationAccess(?User $user, ?string $organizationId): bool
    {
        if ($user === null || blank($organizationId)) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $user->organizations()
            ->wherePivot('is_active', true)
            ->where('organizations.id', $organizationId)
            ->exists();
    }

    public function applyUserOrganizationScope(Builder $query, ?User $user, string $organizationColumn = 'organization_id'): Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->isSuperAdmin($user)) {
            return $query;
        }

        $organizationIds = $this->allowedOrganizationIdsForUser($user);

        if ($organizationIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($organizationColumn, $organizationIds);
    }

    /**
     * @return array<int, string>
     */
    private function activeTenantIdsForUser(User $user): array
    {
        $membershipTenantIds = $user->tenants()
            ->wherePivot('is_active', true)
            ->where('tenants.status', 'active')
            ->pluck('tenants.id')
            ->all();

        $organizationTenantIds = $user->organizations()
            ->wherePivot('is_active', true)
            ->whereNotNull('organizations.tenant_id')
            ->where('organizations.status', 'active')
            ->pluck('organizations.tenant_id')
            ->all();

        return collect($membershipTenantIds)
            ->merge($organizationTenantIds)
            ->filter()
            ->unique()
            ->values()
            ->all();
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
