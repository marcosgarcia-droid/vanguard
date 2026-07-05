<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationAddressRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationContactRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class OrganizationRecordOperationalContactPreferenceTest extends TestCase
{
    public function test_it_prefers_operational_address_over_cnpj_lookup_address(): void
    {
        $fiscalAddress = new OrganizationAddressRecord;
        $fiscalAddress->street = 'Rua Fiscal';
        $fiscalAddress->number = '100';
        $fiscalAddress->district = 'Centro';
        $fiscalAddress->city = 'Cidade Fiscal';
        $fiscalAddress->state = 'MG';
        $fiscalAddress->is_primary = true;
        $fiscalAddress->source = 'cnpj_lookup';

        $operationalAddress = new OrganizationAddressRecord;
        $operationalAddress->street = 'Rua Operacional';
        $operationalAddress->number = '200';
        $operationalAddress->district = 'Distrito Operacional';
        $operationalAddress->city = 'Cidade Operacional';
        $operationalAddress->state = 'SP';
        $operationalAddress->is_primary = false;
        $operationalAddress->source = 'operational_manual';

        $organization = new OrganizationRecord;
        $organization->setRelation('addresses', new Collection([
            $fiscalAddress,
            $operationalAddress,
        ]));

        $this->assertSame('Rua Operacional, 200, Distrito Operacional', $organization->primary_address_line);
        $this->assertSame('Cidade Operacional/SP', $organization->city_state);
    }

    public function test_it_prefers_operational_phone_over_cnpj_lookup_phone(): void
    {
        $fiscalPhone = new OrganizationContactRecord;
        $fiscalPhone->type = 'phone';
        $fiscalPhone->value = '1111111111';
        $fiscalPhone->is_primary = true;
        $fiscalPhone->source = 'cnpj_lookup';

        $operationalPhone = new OrganizationContactRecord;
        $operationalPhone->type = 'phone';
        $operationalPhone->value = '2222222222';
        $operationalPhone->is_primary = false;
        $operationalPhone->source = 'operational_manual';

        $organization = new OrganizationRecord;
        $organization->setRelation('contacts', new Collection([
            $fiscalPhone,
            $operationalPhone,
        ]));

        $this->assertSame('2222222222', $organization->primary_phone_display);
        $this->assertSame('2222222222', $organization->primary_contact_display);
    }

    public function test_it_prefers_operational_email_over_cnpj_lookup_email(): void
    {
        $fiscalEmail = new OrganizationContactRecord;
        $fiscalEmail->type = 'email';
        $fiscalEmail->value = 'fiscal@example.com';
        $fiscalEmail->is_primary = true;
        $fiscalEmail->source = 'cnpj_lookup';

        $operationalEmail = new OrganizationContactRecord;
        $operationalEmail->type = 'email';
        $operationalEmail->value = 'operacional@example.com';
        $operationalEmail->is_primary = false;
        $operationalEmail->source = 'operational_manual';

        $organization = new OrganizationRecord;
        $organization->setRelation('contacts', new Collection([
            $fiscalEmail,
            $operationalEmail,
        ]));

        $this->assertSame('operacional@example.com', $organization->primary_email_display);
    }

    public function test_it_falls_back_to_cnpj_lookup_data_when_operational_data_does_not_exist(): void
    {
        $fiscalPhone = new OrganizationContactRecord;
        $fiscalPhone->type = 'phone';
        $fiscalPhone->value = '1111111111';
        $fiscalPhone->is_primary = true;
        $fiscalPhone->source = 'cnpj_lookup';

        $organization = new OrganizationRecord;
        $organization->setRelation('contacts', new Collection([
            $fiscalPhone,
        ]));

        $this->assertSame('1111111111', $organization->primary_phone_display);
    }
}
