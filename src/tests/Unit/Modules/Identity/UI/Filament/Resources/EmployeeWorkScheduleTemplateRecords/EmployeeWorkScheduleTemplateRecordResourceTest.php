<?php

namespace Tests\Unit\Modules\Identity\UI\Filament\Resources\EmployeeWorkScheduleTemplateRecords;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleTemplateRecord;
use App\Modules\Identity\UI\Filament\Resources\EmployeeWorkScheduleTemplateRecords\EmployeeWorkScheduleTemplateRecordResource;
use Tests\TestCase;

class EmployeeWorkScheduleTemplateRecordResourceTest extends TestCase
{
    public function test_it_uses_the_work_schedule_template_model_and_portuguese_labels(): void
    {
        $this->assertSame(EmployeeWorkScheduleTemplateRecord::class, EmployeeWorkScheduleTemplateRecordResource::getModel());
        $this->assertSame('jornada de trabalho', EmployeeWorkScheduleTemplateRecordResource::getModelLabel());
        $this->assertSame('jornadas de trabalho', EmployeeWorkScheduleTemplateRecordResource::getPluralModelLabel());
        $this->assertSame('Jornadas de trabalho', EmployeeWorkScheduleTemplateRecordResource::getNavigationLabel());
    }
}
