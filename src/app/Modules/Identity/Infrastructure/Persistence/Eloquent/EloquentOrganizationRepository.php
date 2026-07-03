<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Domain\Organizations\Enums\OrganizationStatus;
use App\Modules\Identity\Domain\Organizations\Organization;
use App\Modules\Identity\Domain\Organizations\Repositories\OrganizationRepository;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use App\Modules\Identity\Domain\Organizations\ValueObjects\OrganizationId;

final class EloquentOrganizationRepository implements OrganizationRepository
{
    public function save(Organization $organization): void
    {
        OrganizationRecord::query()->updateOrCreate(
            [
                'id' => $organization->id()->value(),
            ],
            [
                'legal_name' => $organization->legalName(),
                'trade_name' => $organization->tradeName(),
                'status' => $organization->status()->value,
                'cnpj' => $organization->cnpj()?->value(),
                'cnpj_formatted' => $organization->cnpj()?->formatted(),
                'cnpj_root' => $organization->cnpj()?->root(),
                'cnpj_branch' => $organization->cnpj()?->branch(),
                'cnpj_check_digits' => $organization->cnpj()?->checkDigits(),
            ],
        );
    }

    public function findById(OrganizationId $id): ?Organization
    {
        $record = OrganizationRecord::query()->find($id->value());

        if (! $record instanceof OrganizationRecord) {
            return null;
        }

        return new Organization(
            id: new OrganizationId($record->id),
            legalName: $record->legal_name,
            tradeName: $record->trade_name,
            cnpj: Cnpj::fromNullable($record->cnpj),
            status: OrganizationStatus::from($record->status),
        );
    }
}
