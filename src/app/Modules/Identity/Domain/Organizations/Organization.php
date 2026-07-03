<?php

namespace App\Modules\Identity\Domain\Organizations;

use App\Core\Events\RecordsDomainEvents;
use App\Modules\Identity\Domain\Organizations\Enums\OrganizationStatus;
use App\Modules\Identity\Domain\Organizations\Events\OrganizationCreated;
use App\Modules\Identity\Domain\Organizations\ValueObjects\OrganizationId;
use InvalidArgumentException;

final class Organization
{
    use RecordsDomainEvents;

    public function __construct(
        private readonly OrganizationId $id,
        private string $legalName,
        private ?string $tradeName = null,
        private OrganizationStatus $status = OrganizationStatus::Active,
    ) {
        $this->rename($legalName, $tradeName);
    }

    public static function create(
        OrganizationId $id,
        string $legalName,
        ?string $tradeName = null,
        OrganizationStatus $status = OrganizationStatus::Active,
    ): self {
        $organization = new self(
            id: $id,
            legalName: $legalName,
            tradeName: $tradeName,
            status: $status,
        );

        $organization->recordDomainEvent(new OrganizationCreated(
            organizationId: $organization->id(),
            legalName: $organization->legalName(),
            tradeName: $organization->tradeName(),
            status: $organization->status(),
        ));

        return $organization;
    }

    public function id(): OrganizationId
    {
        return $this->id;
    }

    public function legalName(): string
    {
        return $this->legalName;
    }

    public function tradeName(): ?string
    {
        return $this->tradeName;
    }

    public function status(): OrganizationStatus
    {
        return $this->status;
    }

    public function rename(string $legalName, ?string $tradeName = null): void
    {
        $legalName = trim($legalName);
        $tradeName = $tradeName === null ? null : trim($tradeName);

        if ($legalName === '') {
            throw new InvalidArgumentException('Organization legal name cannot be empty.');
        }

        $this->legalName = $legalName;
        $this->tradeName = $tradeName === '' ? null : $tradeName;
    }

    public function activate(): void
    {
        $this->status = OrganizationStatus::Active;
    }

    public function deactivate(): void
    {
        $this->status = OrganizationStatus::Inactive;
    }

    public function isActive(): bool
    {
        return $this->status === OrganizationStatus::Active;
    }
}
