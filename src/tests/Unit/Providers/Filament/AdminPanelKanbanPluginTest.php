<?php

namespace Tests\Unit\Providers\Filament;

use App\Providers\Filament\AdminPanelProvider;
use ReflectionClass;
use Tests\TestCase;

class AdminPanelKanbanPluginTest extends TestCase
{
    public function test_it_registers_the_kanban_plugin_in_the_admin_panel(): void
    {
        $filename = (
            new ReflectionClass(
                AdminPanelProvider::class
            )
        )->getFileName();

        $this->assertIsString($filename);

        $source = file_get_contents($filename);

        $this->assertIsString($source);

        $this->assertStringContainsString(
            'use Wezlo\\FilamentKanban\\FilamentKanbanPlugin;',
            $source
        );

        $this->assertStringContainsString(
            'FilamentKanbanPlugin::make()',
            $source
        );
    }
}
