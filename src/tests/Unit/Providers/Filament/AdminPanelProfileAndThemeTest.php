<?php

declare(strict_types=1);

namespace Tests\Unit\Providers\Filament;

use App\Providers\Filament\AdminPanelProvider;
use Filament\Auth\Pages\EditProfile;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Tests\TestCase;

final class AdminPanelProfileAndThemeTest extends TestCase
{
    public function test_it_enables_the_profile_page_and_uses_the_green_primary_color(): void
    {
        $panel = (
            new AdminPanelProvider(
                $this->app
            )
        )->panel(
            Panel::make()
        );

        $this->assertSame(
            EditProfile::class,
            $panel->getProfilePage()
        );

        $this->assertArrayHasKey(
            'primary',
            $panel->getColors()
        );

        $this->assertSame(
            Color::Green,
            $panel->getColors()['primary']
        );
    }
}
