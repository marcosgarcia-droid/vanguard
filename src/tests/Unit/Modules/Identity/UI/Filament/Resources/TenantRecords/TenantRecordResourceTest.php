<?php

namespace Tests\Unit\Modules\Identity\UI\Filament\Resources\TenantRecords;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Identity\UI\Filament\Resources\TenantRecords\TenantRecordResource;
use Tests\TestCase;

class TenantRecordResourceTest extends TestCase
{
    public function test_it_uses_the_tenant_model_and_portuguese_labels(): void
    {
        $this->assertSame(TenantRecord::class, TenantRecordResource::getModel());
        $this->assertSame('Grupos empresariais', TenantRecordResource::getNavigationLabel());
        $this->assertSame('Acesso', TenantRecordResource::getNavigationGroup());
        $this->assertSame('grupo empresarial', TenantRecordResource::getModelLabel());
        $this->assertSame('grupos empresariais', TenantRecordResource::getPluralModelLabel());
    }
}
