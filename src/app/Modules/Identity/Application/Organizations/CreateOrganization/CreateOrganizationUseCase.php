<?php

namespace App\Modules\Identity\Application\Organizations\CreateOrganization;

use App\Modules\Identity\Domain\Organizations\Organization;
use App\Modules\Identity\Domain\Organizations\Repositories\OrganizationRepository;
use App\Modules\Identity\Domain\Organizations\ValueObjects\OrganizationId;
use App\Support\Contracts\DomainEventDispatcher;
use App\Support\Contracts\TransactionManager;
use App\Support\Contracts\UseCase;

final readonly class CreateOrganizationUseCase implements UseCase
{
    public function __construct(
        private OrganizationRepository $organizations,
        private DomainEventDispatcher $events,
        private TransactionManager $transactions,
    ) {}

    public function execute(CreateOrganizationCommand $command): Organization
    {
        return $this->transactions->run(function () use ($command): Organization {
            $organization = Organization::create(
                id: new OrganizationId($command->organizationId),
                legalName: $command->legalName,
                tradeName: $command->tradeName,
            );

            $this->organizations->save($organization);

            $this->events->dispatchMany($organization->releaseDomainEvents());

            return $organization;
        });
    }
}
