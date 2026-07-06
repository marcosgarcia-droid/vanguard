<?php

namespace Tests\Unit\Modules\Identity\UI\Filament\Resources\EmployeeRecords;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\EmployeeRecordResource;
use Tests\TestCase;

class EmployeeRecordResourceTest extends TestCase
{
    public function test_it_uses_the_employee_model_and_portuguese_labels(): void
    {
        $this->assertSame(EmployeeRecord::class, EmployeeRecordResource::getModel());
        $this->assertSame('Funcionários', EmployeeRecordResource::getNavigationLabel());
        $this->assertSame('Cadastros', EmployeeRecordResource::getNavigationGroup());
        $this->assertSame('funcionário', EmployeeRecordResource::getModelLabel());
        $this->assertSame('funcionários', EmployeeRecordResource::getPluralModelLabel());
    }
}
