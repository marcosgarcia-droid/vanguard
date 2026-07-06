<?php

namespace Tests\Unit\Modules\Identity\UI\Filament\Resources\ClassificationOptionRecords;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\ClassificationOptionRecord;
use App\Modules\Identity\UI\Filament\Resources\ClassificationOptionRecords\ClassificationOptionRecordResource;
use Tests\TestCase;

class ClassificationOptionRecordResourceTest extends TestCase
{
    public function test_it_uses_the_classification_model_and_portuguese_labels(): void
    {
        $this->assertSame(ClassificationOptionRecord::class, ClassificationOptionRecordResource::getModel());
        $this->assertSame('classificação', ClassificationOptionRecordResource::getModelLabel());
        $this->assertSame('classificações', ClassificationOptionRecordResource::getPluralModelLabel());
        $this->assertSame('Classificações', ClassificationOptionRecordResource::getNavigationLabel());
    }
}
