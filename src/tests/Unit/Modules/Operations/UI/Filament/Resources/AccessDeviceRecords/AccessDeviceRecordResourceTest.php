<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\AccessDeviceRecords;

use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\UI\Filament\Resources\AccessDeviceRecords\AccessDeviceRecordResource;
use Tests\TestCase;

class AccessDeviceRecordResourceTest extends TestCase
{
    public function test_it_uses_the_access_device_model_and_portuguese_labels(): void
    {
        $this->assertSame(
            AccessDeviceRecord::class,
            AccessDeviceRecordResource::getModel()
        );

        $this->assertSame(
            'dispositivo de acesso',
            AccessDeviceRecordResource::getModelLabel()
        );

        $this->assertSame(
            'dispositivos de acesso',
            AccessDeviceRecordResource::getPluralModelLabel()
        );

        $this->assertSame(
            'Dispositivos',
            AccessDeviceRecordResource::getNavigationLabel()
        );

        $this->assertSame(
            'Controle de acesso',
            AccessDeviceRecordResource::getNavigationGroup()
        );
    }
}
