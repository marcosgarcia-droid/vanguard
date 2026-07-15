<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\AccessDeviceRecords;

use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\AccessDeviceConfigurationCatalog;
use App\Modules\Operations\UI\Filament\Resources\AccessDeviceRecords\Schemas\AccessDeviceConfigurationSchema;
use Closure;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use ReflectionClass;
use Tests\TestCase;

class AccessDeviceConfigurationSchemaTest extends TestCase
{
    public function test_it_organizes_the_equipment_configuration_into_nested_tabs(): void
    {
        $expectedLabels = collect(
            AccessDeviceConfigurationCatalog::grouped()
        )
            ->pluck('label')
            ->values()
            ->all();

        $this->assertCount(
            8,
            $expectedLabels
        );

        $this->assertTabbedSchema(
            AccessDeviceConfigurationSchema::formSections(),
            $expectedLabels
        );

        $this->assertTabbedSchema(
            AccessDeviceConfigurationSchema::infolistSections(),
            $expectedLabels
        );
    }

    /**
     * @param  array<int, mixed>  $schema
     * @param  array<int, string>  $expectedLabels
     */
    private function assertTabbedSchema(
        array $schema,
        array $expectedLabels
    ): void {
        $this->assertCount(
            1,
            $schema
        );

        $tabs = $schema[0];

        $this->assertInstanceOf(
            Tabs::class,
            $tabs
        );

        $tabComponents =
            $this->declaredChildComponents($tabs);

        $this->assertCount(
            8,
            $tabComponents
        );

        foreach ($tabComponents as $tab) {
            $this->assertInstanceOf(
                Tab::class,
                $tab
            );
        }

        $this->assertSame(
            $expectedLabels,
            collect($tabComponents)
                ->map(
                    fn (Tab $tab): string => $tab->getLabel()
                )
                ->values()
                ->all()
        );
    }

    /**
     * Obtém os componentes declarados sem conectar o componente
     * ao container de renderização do Filament.
     *
     * @return array<int, Component>
     */
    private function declaredChildComponents(
        Component $component
    ): array {
        $reflection = new ReflectionClass(
            $component
        );

        while (
            ! $reflection->hasProperty(
                'childComponents'
            )
        ) {
            $parent = $reflection->getParentClass();

            if ($parent === false) {
                $this->fail(
                    'A propriedade childComponents não foi encontrada.'
                );
            }

            $reflection = $parent;
        }

        $property = $reflection->getProperty(
            'childComponents'
        );

        $property->setAccessible(true);

        $declaredComponents = $property->getValue(
            $component
        );

        $this->assertIsArray(
            $declaredComponents
        );

        $components =
            $declaredComponents['default'] ?? [];

        if ($components instanceof Closure) {
            $components = $components();
        }

        $this->assertIsArray(
            $components
        );

        return array_values(
            $components
        );
    }
}
