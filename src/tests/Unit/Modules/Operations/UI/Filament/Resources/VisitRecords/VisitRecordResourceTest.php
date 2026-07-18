<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitRecords;

use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\VisitRecordResource;
use Tests\TestCase;

class VisitRecordResourceTest extends TestCase
{
    public function test_it_uses_the_visit_model_and_portuguese_labels(): void
    {
        $this->assertSame(
            VisitRecord::class,
            VisitRecordResource::getModel()
        );

        $this->assertSame(
            'visita',
            VisitRecordResource::getModelLabel()
        );

        $this->assertSame(
            'visitas',
            VisitRecordResource::getPluralModelLabel()
        );

        $this->assertSame(
            'Visitas',
            VisitRecordResource::getNavigationLabel()
        );

        $this->assertArrayHasKey(
            'index',
            VisitRecordResource::getPages()
        );
    }
}
