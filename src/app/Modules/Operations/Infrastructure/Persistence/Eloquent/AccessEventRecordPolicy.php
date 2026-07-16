<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;

final class AccessEventRecordPolicy
{
    /**
     * Eventos são registros técnicos e auditáveis.
     * Nenhuma alteração ou exclusão direta é permitida.
     *
     * @var array<int, string>
     */
    private const BLOCKED_ABILITIES = [
        'create',
        'update',
        'delete',
        'deleteAny',
        'restore',
        'restoreAny',
        'forceDelete',
        'forceDeleteAny',
    ];

    public function before(
        User $user,
        string $ability
    ): ?bool {
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
        return $user->can(
            'ViewAny:AccessEventRecord'
        );
    }

    public function view(
        User $user,
        AccessEventRecord $event
    ): bool {
        return $user->can(
            'View:AccessEventRecord'
        )
            && $this->canAccessRecord(
                $user,
                $event
            );
    }

    public function reprocessFlow(
        User $user,
        AccessEventRecord $event
    ): bool {
        return $user->can(
            'ReprocessFlow:AccessEventRecord'
        )
            && $this->canAccessRecord(
                $user,
                $event
            );
    }

    public function associateManually(
        User $user,
        AccessEventRecord $event
    ): bool {
        return $user->can(
            'AssociateManually:AccessEventRecord'
        )
            && $this->canAccessRecord(
                $user,
                $event
            );
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(
        User $user,
        AccessEventRecord $event
    ): bool {
        return false;
    }

    public function delete(
        User $user,
        AccessEventRecord $event
    ): bool {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(
        User $user,
        AccessEventRecord $event
    ): bool {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(
        User $user,
        AccessEventRecord $event
    ): bool {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    private function canAccessRecord(
        User $user,
        AccessEventRecord $event
    ): bool {
        if (
            blank($event->tenant_id)
            || blank($event->organization_id)
        ) {
            return false;
        }

        $tenantContext = app(
            TenantContext::class
        );

        $hasTenantAccess = $tenantContext
            ->availableTenantsForUser($user)
            ->contains(
                'id',
                $event->tenant_id
            );

        if (! $hasTenantAccess) {
            return false;
        }

        return $tenantContext
            ->hasOrganizationAccess(
                $user,
                $event->organization_id
            );
    }
}
