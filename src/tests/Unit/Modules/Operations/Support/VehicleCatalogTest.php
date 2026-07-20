<?php

namespace Tests\Unit\Modules\Operations\Support;

use App\Modules\Operations\Support\VehicleCatalog;
use Tests\TestCase;

class VehicleCatalogTest extends TestCase
{
    public function test_brand_options_include_relevant_brands_and_manual_option(): void
    {
        $options = VehicleCatalog::brandOptions();

        $this->assertSame('Chevrolet', $options['Chevrolet']);
        $this->assertSame('Fiat', $options['Fiat']);
        $this->assertSame('Toyota', $options['Toyota']);
        $this->assertSame('Volkswagen', $options['Volkswagen']);
        $this->assertSame('BYD', $options['BYD']);
        $this->assertSame(
            'Outra marca',
            $options[VehicleCatalog::OTHER]
        );
    }

    public function test_model_options_depend_on_selected_brand(): void
    {
        $toyota = VehicleCatalog::modelOptions('Toyota');
        $volkswagen = VehicleCatalog::modelOptions('Volkswagen');

        $this->assertSame('Corolla', $toyota['Corolla']);
        $this->assertSame('Hilux', $toyota['Hilux']);
        $this->assertSame(
            'Corolla Cross',
            $toyota['Corolla Cross']
        );

        $this->assertSame('Nivus', $volkswagen['Nivus']);
        $this->assertSame('T-Cross', $volkswagen['T-Cross']);
        $this->assertSame('Taos', $volkswagen['Taos']);

        $this->assertArrayNotHasKey('Corolla', $volkswagen);
        $this->assertSame(
            'Outro modelo',
            $toyota[VehicleCatalog::OTHER]
        );
    }

    public function test_model_options_are_empty_without_catalog_brand(): void
    {
        $this->assertSame(
            [],
            VehicleCatalog::modelOptions(null)
        );

        $this->assertSame(
            [],
            VehicleCatalog::modelOptions('')
        );

        $this->assertSame(
            [],
            VehicleCatalog::modelOptions(VehicleCatalog::OTHER)
        );

        $this->assertSame(
            [],
            VehicleCatalog::modelOptions('Marca inexistente')
        );
    }

    public function test_color_options_include_operational_colors_and_manual_option(): void
    {
        $options = VehicleCatalog::colorOptions();

        $this->assertSame('Branco', $options['Branco']);
        $this->assertSame('Cinza', $options['Cinza']);
        $this->assertSame('Prata', $options['Prata']);
        $this->assertSame('Preto', $options['Preto']);
        $this->assertSame(
            'Outra cor',
            $options[VehicleCatalog::OTHER]
        );
    }

    public function test_manual_and_catalog_selections_are_resolved_safely(): void
    {
        $this->assertSame(
            'Toyota',
            VehicleCatalog::resolveSelection('Toyota', null)
        );

        $this->assertSame(
            'Marca artesanal',
            VehicleCatalog::resolveSelection(
                VehicleCatalog::OTHER,
                '  Marca artesanal  '
            )
        );

        $this->assertNull(
            VehicleCatalog::resolveSelection(
                VehicleCatalog::OTHER,
                '   '
            )
        );

        $this->assertNull(
            VehicleCatalog::resolveSelection(null, null)
        );
    }

    public function test_catalog_can_validate_brand_and_model_relationship(): void
    {
        $this->assertTrue(
            VehicleCatalog::hasBrand('Toyota')
        );

        $this->assertFalse(
            VehicleCatalog::hasBrand('Marca inexistente')
        );

        $this->assertTrue(
            VehicleCatalog::hasModel('Toyota', 'Corolla')
        );

        $this->assertFalse(
            VehicleCatalog::hasModel('Toyota', 'Onix')
        );
    }
}
