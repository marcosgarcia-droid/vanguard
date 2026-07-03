<?php

namespace App\Modules\Identity\Infrastructure\Persistence\InMemory;

use App\Modules\Identity\Domain\Organizations\Organization;
use App\Modules\Identity\Domain\Organizations\Repositories\OrganizationRepository;
use App\Modules\Identity\Domain\Organizations\ValueObjects\OrganizationId;

final class InMemoryOrganizationRepository implements OrganizationRepository
{
    /**
     * @var array<string, Organization>
     */
    private array $organizations = [];

    public function save(Organization $organization): void
    {
        $this->organizations[$organization->id()->value()] = $organization;
    }

    public function findById(OrganizationId $id): ?Organization
    {
        return $this->organizations[$id->value()] ?? null;
    }
}
