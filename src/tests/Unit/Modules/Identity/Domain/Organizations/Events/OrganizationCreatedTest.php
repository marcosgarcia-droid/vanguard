<?php

namespace Tests\Unit\Modules\Identity\Domain\Organizations\Events;

use App\Modules\Identity\Domain\Organizations\Enums\OrganizationStatus;
use App\Modules\Identity\Domain\Organizations\Events\OrganizationCreated;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
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
            cnpj: new Cnpj('11.222.333/0001-81'),
            occurredAt: $occurredAt,
        );

        $this->assertSame(OrganizationCreated::class, $event->name());
        $this->assertSame('org-001', $event->organizationId()->value());
        $this->assertSame('Agronorte Distribuidora', $event->legalName());
        $this->assertSame('Agronorte', $event->tradeName());
        $this->assertSame(OrganizationStatus::Active, $event->status());
        $this->assertSame('11222333000181', $event->cnpj()?->value());
        $this->assertSame($occurredAt, $event->occurredAt());

        $this->assertSame([
            'organization_id' => 'org-001',
            'legal_name' => 'Agronorte Distribuidora',
            'trade_name' => 'Agronorte',
            'status' => 'active',
            'cnpj' => '11222333000181',
            'cnpj_formatted' => '11.222.333/0001-81',
            'cnpj_root' => '11222333',
            'cnpj_branch' => '0001',
            'cnpj_check_digits' => '81',
        ], $event->payload());
    }

    public function test_cnpj_is_optional(): void
    {
        $event = new OrganizationCreated(
            organizationId: new OrganizationId('org-001'),
            legalName: 'Agronorte Distribuidora',
            tradeName: 'Agronorte',
            status: OrganizationStatus::Active,
        );

        $this->assertNull($event->cnpj());

        $this->assertSame([
            'organization_id' => 'org-001',
            'legal_name' => 'Agronorte Distribuidora',
            'trade_name' => 'Agronorte',
            'status' => 'active',
            'cnpj' => null,
            'cnpj_formatted' => null,
            'cnpj_root' => null,
            'cnpj_branch' => null,
            'cnpj_check_digits' => null,
        ], $event->payload());
    }
}
