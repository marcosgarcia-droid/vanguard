<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;

final class AccessDeviceRecordPolicy
{
    /**
     * @var array<int, string>
     */
    private const BLOCKED_ABILITIES = [
        'delete',
        'deleteAny',
        'restore',
        'restoreAny',
        'forceDelete',
        'forceDeleteAny',
    ];

    public function before(User $user, string $ability): ?bool
    {
        if (! $user->hasRole(
            config(
                'filament-shield.super_admin.name',
                'super_admin'
            )
        )) {
            return null;
        }

        return in_array(
            $ability,
            self::BLOCKED_ABILITIES,
            true
        )
            ? false
            : true;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:AccessDeviceRecord');
    }

    public function view(
        User $user,
        AccessDeviceRecord $device
    ): bool {
        return $user->can('View:AccessDeviceRecord')
            && $this->canAccessRecord($user, $device);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:AccessDeviceRecord')
            && app(TenantContext::class)
                ->currentTenantIdForUser($user) !== null;
    }

    public function update(
        User $user,
        AccessDeviceRecord $device
    ): bool {
        return $user->can('Update:AccessDeviceRecord')
            && $this->canAccessRecord($user, $device);
    }

    public function readConfiguration(
        User $user,
        AccessDeviceRecord $device
    ): bool {
        return $user->can(
            'ReadConfiguration:AccessDeviceRecord'
        )
            && $this->canAccessRecord(
                $user,
                $device
            );
    }

    public function delete(
        User $user,
        AccessDeviceRecord $device
    ): bool {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(
        User $user,
        AccessDeviceRecord $device
    ): bool {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(
        User $user,
        AccessDeviceRecord $device
    ): bool {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    private function canAccessRecord(
        User $user,
        AccessDeviceRecord $device
    ): bool {
        if (
            blank($device->tenant_id)
            || blank($device->organization_id)
        ) {
            return false;
        }

        $tenantContext = app(TenantContext::class);

        $hasTenantAccess = $tenantContext
            ->availableTenantsForUser($user)
            ->contains('id', $device->tenant_id);

        if (! $hasTenantAccess) {
            return false;
        }

        return $tenantContext->hasOrganizationAccess(
            $user,
            $device->organization_id
        );
    }
}
