<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasDeviceModel;
use InvalidArgumentException;
use Tests\TestCase;

final class IntelbrasDeviceModelTest extends TestCase
{
    public function test_it_normalizes_the_installed_model(): void
    {
        $model = new IntelbrasDeviceModel(
            '  ss-3532_mf  '
        );

        $this->assertSame(
            'SS 3532 MF',
            $model->value
        );
    }

    public function test_it_keeps_adjacent_models_distinct(): void
    {
        $installed = new IntelbrasDeviceModel(
            'SS 3532 MF'
        );

        $wireless = new IntelbrasDeviceModel(
            'SS 3532 MF W'
        );

        $this->assertFalse(
            $installed->equals($wireless)
        );
    }

    public function test_it_rejects_an_empty_model(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new IntelbrasDeviceModel('   ');
    }

    public function test_it_rejects_control_characters(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new IntelbrasDeviceModel(
            "SS 3532 \x01 MF"
        );
    }
}
