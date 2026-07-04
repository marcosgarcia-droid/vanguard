<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationContactRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class OrganizationRecordOperationalIdentityTest extends TestCase
{
    public function test_primary_email_does_not_fallback_to_phone(): void
    {
        $organization = new OrganizationRecord([
            'id' => 'organization-01',
            'legal_name' => 'AGRONORTE NUTRICAO ANIMAL LTDA',
        ]);

        $phone = new OrganizationContactRecord;
        $phone->type = 'phone';
        $phone->value = '6334712155';
        $phone->is_primary = true;

        $organization->setRelation('contacts', new Collection([$phone]));

        $this->assertSame('6334712155', $organization->primary_phone_display);
        $this->assertNull($organization->primary_email_display);
        $this->assertSame('6334712155', $organization->primary_contact_display);
    }
}
