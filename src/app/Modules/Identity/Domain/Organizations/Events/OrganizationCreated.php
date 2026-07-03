<?php

namespace App\Modules\Identity\Domain\Organizations\Events;

use App\Core\Events\BaseDomainEvent;
use App\Modules\Identity\Domain\Organizations\Enums\OrganizationStatus;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use App\Modules\Identity\Domain\Organizations\ValueObjects\OrganizationId;
use DateTimeImmutable;

final class OrganizationCreated extends BaseDomainEvent
{
    public function __construct(
        private readonly OrganizationId $organizationId,
        private readonly string $legalName,
        private readonly ?string $tradeName,
        private readonly OrganizationStatus $status,
        private readonly ?Cnpj $cnpj = null,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        parent::__construct($occurredAt);
    }

    public function organizationId(): OrganizationId
    {
        return $this->organizationId;
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

    public function cnpj(): ?Cnpj
    {
        return $this->cnpj;
    }

    public function payload(): array
    {
        return [
            'organization_id' => $this->organizationId->value(),
            'legal_name' => $this->legalName,
            'trade_name' => $this->tradeName,
            'status' => $this->status->value,
            'cnpj' => $this->cnpj?->value(),
            'cnpj_formatted' => $this->cnpj?->formatted(),
            'cnpj_root' => $this->cnpj?->root(),
            'cnpj_branch' => $this->cnpj?->branch(),
            'cnpj_check_digits' => $this->cnpj?->checkDigits(),
        ];
    }
}
