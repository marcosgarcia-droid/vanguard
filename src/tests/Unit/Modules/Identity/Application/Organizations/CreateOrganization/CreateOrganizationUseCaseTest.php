<?php

namespace Tests\Unit\Modules\Identity\Application\Organizations\CreateOrganization;

use App\Modules\Identity\Application\Organizations\CreateOrganization\CreateOrganizationCommand;
use App\Modules\Identity\Application\Organizations\CreateOrganization\CreateOrganizationUseCase;
use App\Modules\Identity\Domain\Organizations\Events\OrganizationCreated;
use App\Modules\Identity\Domain\Organizations\Organization;
use App\Modules\Identity\Domain\Organizations\Repositories\OrganizationRepository;
use App\Modules\Identity\Domain\Organizations\ValueObjects\OrganizationId;
use App\Support\Contracts\DomainEvent;
use App\Support\Contracts\DomainEventDispatcher;
use App\Support\Contracts\TransactionManager;
use PHPUnit\Framework\TestCase;

class CreateOrganizationUseCaseTest extends TestCase
{
    public function test_it_creates_saves_and_dispatches_organization_events(): void
    {
        $repository = new class implements OrganizationRepository
        {
            /**
             * @var array<string, Organization>
             */
            public array $organizations = [];

            public function save(Organization $organization): void
            {
                $this->organizations[$organization->id()->value()] = $organization;
            }

            public function findById(OrganizationId $id): ?Organization
            {
                return $this->organizations[$id->value()] ?? null;
            }
        };

        $dispatcher = new class implements DomainEventDispatcher
        {
            /**
             * @var list<DomainEvent>
             */
            public array $events = [];

            public function dispatch(DomainEvent $event): void
            {
                $this->events[] = $event;
            }

            public function dispatchMany(iterable $events): void
            {
                foreach ($events as $event) {
                    $this->dispatch($event);
                }
            }
        };

        $transactions = new class implements TransactionManager
        {
            public int $runs = 0;

            public function run(callable $callback): mixed
            {
                $this->runs++;

                return $callback();
            }
        };

        $useCase = new CreateOrganizationUseCase(
            organizations: $repository,
            events: $dispatcher,
            transactions: $transactions,
        );

        $organization = $useCase->execute(new CreateOrganizationCommand(
            organizationId: 'org-001',
            legalName: 'Agronorte Distribuidora',
            tradeName: 'Agronorte',
        ));

        $this->assertSame('org-001', $organization->id()->value());
        $this->assertSame('Agronorte Distribuidora', $organization->legalName());
        $this->assertSame('Agronorte', $organization->tradeName());

        $this->assertSame($organization, $repository->findById(new OrganizationId('org-001')));
        $this->assertSame(1, $transactions->runs);

        $this->assertCount(1, $dispatcher->events);
        $this->assertInstanceOf(OrganizationCreated::class, $dispatcher->events[0]);

        $this->assertSame([], $organization->releaseDomainEvents());
    }
}
