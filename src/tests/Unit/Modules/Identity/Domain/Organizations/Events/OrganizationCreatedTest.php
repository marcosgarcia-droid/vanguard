<?php

namespace Tests\Unit\Modules\Identity\Domain\Organizations\Events;

use App\Modules\Identity\Domain\Organizations\Enums\OrganizationStatus;
use App\Modules\Identity\Domain\Organizations\Events\OrganizationCreated;
use App\Modules\Identity\Domain\Organizations\ValueObjects\OrganizationId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class OrganizationCreatedTest extends TestCase
{
    public function test_it_exposes_organization_created_event_data(): void
    {
        $occurredAt = new DateTimeImmutable('2026-01-01 10:00:00');

        $event = new OrganizationCreated(
            organizationId: new OrganizationId('org-001'),
            legalName: 'Agronorte Distribuidora',
            tradeName: 'Agronorte',
            status: OrganizationStatus::Active,
            occurredAt: $occurredAt,
        );

        $this->assertSame(OrganizationCreated::class, $event->name());
        $this->assertSame('org-001', $event->organizationId()->value());
        $this->assertSame('Agronorte Distribuidora', $event->legalName());
        $this->assertSame('Agronorte', $event->tradeName());
        $this->assertSame(OrganizationStatus::Active, $event->status());
        $this->assertSame($occurredAt, $event->occurredAt());
        $this->assertSame([
            'organization_id' => 'org-001',
            'legal_name' => 'Agronorte Distribuidora',
            'trade_name' => 'Agronorte',
            'status' => 'active',
        ], $event->payload());
    }
}
