<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\AccessEventRecords;

use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\AccessEventRecordResource;
use Tests\TestCase;

class AccessEventRecordResourceTest extends TestCase
{
    public function test_it_uses_the_access_event_model_and_portuguese_labels(): void
    {
        $this->assertSame(
            AccessEventRecord::class,
            AccessEventRecordResource::getModel()
        );

        $this->assertSame(
            'evento de acesso',
            AccessEventRecordResource::getModelLabel()
        );

        $this->assertSame(
            'eventos de acesso',
            AccessEventRecordResource::getPluralModelLabel()
        );

        $this->assertSame(
            'Eventos de acesso',
            AccessEventRecordResource::getNavigationLabel()
        );

        $this->assertSame(
            'Controle de acesso',
            AccessEventRecordResource::getNavigationGroup()
        );

        $this->assertSame(
            'eventos-de-acesso',
            AccessEventRecordResource::getSlug()
        );
    }

    public function test_it_exposes_only_the_list_page_and_blocks_mutating_actions(): void
    {
        $this->assertSame(
            ['index'],
            array_keys(
                AccessEventRecordResource::getPages()
            )
        );

        $this->assertFalse(
            AccessEventRecordResource::canCreate()
        );

        $this->assertFalse(
            AccessEventRecordResource::canDeleteAny()
        );

        $this->assertFalse(
            AccessEventRecordResource::canRestoreAny()
        );

        $this->assertFalse(
            AccessEventRecordResource::canForceDeleteAny()
        );
    }
}
