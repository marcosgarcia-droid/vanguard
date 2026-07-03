<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationAddressRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationCnaeActivityRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationCnpjSyncRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationContactRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationMemberRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationTaxRegimeRecord;
use PHPUnit\Framework\TestCase;

class IdentityOrganizationEloquentRecordsTest extends TestCase
{
    public function test_organization_record_uses_string_primary_key(): void
    {
        $record = new OrganizationRecord;

        $this->assertSame('organizations', $record->getTable());
        $this->assertSame('id', $record->getKeyName());
        $this->assertSame('string', $record->getKeyType());
        $this->assertFalse($record->getIncrementing());
    }

    public function test_records_point_to_expected_tables(): void
    {
        $this->assertSame('organization_addresses', (new OrganizationAddressRecord)->getTable());
        $this->assertSame('organization_contacts', (new OrganizationContactRecord)->getTable());
        $this->assertSame('organization_cnae_activities', (new OrganizationCnaeActivityRecord)->getTable());
        $this->assertSame('organization_members', (new OrganizationMemberRecord)->getTable());
        $this->assertSame('organization_tax_regimes', (new OrganizationTaxRegimeRecord)->getTable());
        $this->assertSame('organization_cnpj_syncs', (new OrganizationCnpjSyncRecord)->getTable());
    }

    public function test_organization_record_has_expected_business_fields(): void
    {
        $fillable = (new OrganizationRecord)->getFillable();

        $this->assertContains('cnpj', $fillable);
        $this->assertContains('legal_name', $fillable);
        $this->assertContains('trade_name', $fillable);
        $this->assertContains('tax_registration_status_code', $fillable);
        $this->assertContains('legal_nature_code', $fillable);
        $this->assertContains('company_size_code', $fillable);
        $this->assertContains('share_capital', $fillable);
        $this->assertContains('cnpj_normalized_data', $fillable);
    }

    public function test_organization_record_casts_business_fields(): void
    {
        $casts = (new OrganizationRecord)->getCasts();

        $this->assertSame('boolean', $casts['is_head_office']);
        $this->assertSame('date', $casts['opened_at']);
        $this->assertSame('decimal:2', $casts['share_capital']);
        $this->assertSame('datetime', $casts['cnpj_synced_at']);
        $this->assertSame('array', $casts['cnpj_normalized_data']);
    }
}
