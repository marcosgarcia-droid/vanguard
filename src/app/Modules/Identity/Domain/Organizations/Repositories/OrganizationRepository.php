<?php

namespace App\Modules\Identity\Domain\Organizations\Repositories;

use App\Modules\Identity\Domain\Organizations\Organization;
use App\Modules\Identity\Domain\Organizations\ValueObjects\OrganizationId;

interface OrganizationRepository
{
    public function save(Organization $organization): void;

    public function findById(OrganizationId $id): ?Organization;
}
