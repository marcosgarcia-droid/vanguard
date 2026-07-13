<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\VisitorRecordResource;
use Tests\TestCase;

class VisitorRecordResourceTest extends TestCase
{
    public function test_it_uses_the_visitor_model_and_portuguese_labels(): void
    {
        $this->assertSame(
            VisitorRecord::class,
            VisitorRecordResource::getModel()
        );

        $this->assertSame(
            'visitante',
            VisitorRecordResource::getModelLabel()
        );

        $this->assertSame(
            'visitantes',
            VisitorRecordResource::getPluralModelLabel()
        );

        $this->assertSame(
            'Visitantes',
            VisitorRecordResource::getNavigationLabel()
        );
    }
}
