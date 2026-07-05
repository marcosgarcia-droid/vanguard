<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationAddressRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationContactRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class OrganizationRecordFiscalOperationalDisplayTest extends TestCase
{
    public function test_it_exposes_operational_and_fiscal_data_separately(): void
    {
        $fiscalAddress = new OrganizationAddressRecord;
        $fiscalAddress->street = 'Rua Fiscal';
        $fiscalAddress->number = '100';
        $fiscalAddress->district = 'Centro Fiscal';
        $fiscalAddress->city = 'Cidade Fiscal';
        $fiscalAddress->state = 'MG';
        $fiscalAddress->postal_code = '11111111';
        $fiscalAddress->is_primary = true;
        $fiscalAddress->source = 'cnpj_lookup';

        $operationalAddress = new OrganizationAddressRecord;
        $operationalAddress->street = 'Rua Operacional';
        $operationalAddress->number = '200';
        $operationalAddress->district = 'Centro Operacional';
        $operationalAddress->city = 'Cidade Operacional';
        $operationalAddress->state = 'SP';
        $operationalAddress->postal_code = '22222222';
        $operationalAddress->is_primary = true;
        $operationalAddress->source = 'operational_manual';

        $fiscalPhone = new OrganizationContactRecord;
        $fiscalPhone->type = 'phone';
        $fiscalPhone->value = '1111111111';
        $fiscalPhone->is_primary = true;
        $fiscalPhone->source = 'cnpj_lookup';

        $operationalPhone = new OrganizationContactRecord;
        $operationalPhone->type = 'phone';
        $operationalPhone->value = '2222222222';
        $operationalPhone->is_primary = true;
        $operationalPhone->source = 'operational_manual';

        $fiscalEmail = new OrganizationContactRecord;
        $fiscalEmail->type = 'email';
        $fiscalEmail->value = 'fiscal@example.com';
        $fiscalEmail->is_primary = true;
        $fiscalEmail->source = 'cnpj_lookup';

        $operationalEmail = new OrganizationContactRecord;
        $operationalEmail->type = 'email';
        $operationalEmail->value = 'operacional@example.com';
        $operationalEmail->is_primary = true;
        $operationalEmail->source = 'operational_manual';

        $organization = new OrganizationRecord;
        $organization->setRelation('addresses', new Collection([
            $fiscalAddress,
            $operationalAddress,
        ]));
        $organization->setRelation('contacts', new Collection([
            $fiscalPhone,
            $operationalPhone,
            $fiscalEmail,
            $operationalEmail,
        ]));

        $this->assertSame('2222222222', $organization->primary_phone_display);
        $this->assertSame('operacional@example.com', $organization->primary_email_display);
        $this->assertSame('Rua Operacional, 200, Centro Operacional', $organization->primary_address_line);

        $this->assertSame('2222222222', $organization->operational_phone);
        $this->assertSame('operacional@example.com', $organization->operational_email);
        $this->assertSame('Rua Operacional, 200, Centro Operacional', $organization->operational_address_line);
        $this->assertSame('Cidade Operacional/SP', $organization->operational_city_state);
        $this->assertSame('22222222', $organization->operational_postal_code);

        $this->assertSame('1111111111', $organization->fiscal_phone_display);
        $this->assertSame('fiscal@example.com', $organization->fiscal_email_display);
        $this->assertSame('Rua Fiscal, 100, Centro Fiscal', $organization->fiscal_address_line);
        $this->assertSame('Cidade Fiscal/MG', $organization->fiscal_city_state);
        $this->assertSame('11111111', $organization->fiscal_postal_code);
    }
}
