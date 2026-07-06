<?php

namespace Tests\Unit\Modules\Identity\UI\Filament\Resources\PartnerRecords;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Identity\UI\Filament\Resources\PartnerRecords\PartnerRecordResource;
use Tests\TestCase;

class PartnerRecordResourceTest extends TestCase
{
    public function test_it_uses_the_partner_model_and_portuguese_labels(): void
    {
        $this->assertSame(PartnerRecord::class, PartnerRecordResource::getModel());
        $this->assertSame('parceiro', PartnerRecordResource::getModelLabel());
        $this->assertSame('parceiros', PartnerRecordResource::getPluralModelLabel());
        $this->assertSame('Parceiros', PartnerRecordResource::getNavigationLabel());
    }
}
