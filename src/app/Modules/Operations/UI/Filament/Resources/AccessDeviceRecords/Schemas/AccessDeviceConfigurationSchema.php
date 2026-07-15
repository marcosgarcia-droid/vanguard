<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessDeviceRecords\Schemas;

use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\AccessDeviceConfigurationCatalog;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\AccessDeviceConfigurationPresenter;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use Filament\Forms\Components\Placeholder;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

final class AccessDeviceConfigurationSchema
{
    /**
     * @return array<int, Tabs>
     */
    public static function formSections(): array
    {
        return [
            Tabs::make('Áreas de configuração')
                ->id(
                    'access-device-equipment-configuration-form-tabs'
                )
                ->persistTab()
                ->tabs(
                    collect(
                        AccessDeviceConfigurationCatalog::grouped()
                    )
                        ->map(
                            fn (array $category): Tab => self::formTab(
                                $category
                            )
                        )
                        ->values()
                        ->all()
                )
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Tabs>
     */
    public static function infolistSections(): array
    {
        return [
            Tabs::make('Áreas de configuração')
                ->id(
                    'access-device-equipment-configuration-infolist-tabs'
                )
                ->persistTab()
                ->tabs(
                    collect(
                        AccessDeviceConfigurationCatalog::grouped()
                    )
                        ->map(
                            fn (array $category): Tab => self::infolistTab(
                                $category
                            )
                        )
                        ->values()
                        ->all()
                )
                ->columnSpanFull(),
        ];
    }

    /**
     * @param array{
     *     label: string,
     *     description: string,
     *     items: array<int, array<string, mixed>>
     * } $category
     */
    private static function formTab(
        array $category
    ): Tab {
        return Tab::make(
            $category['label']
        )
            ->schema([
                self::formSection($category),
            ]);
    }

    /**
     * @param array{
     *     label: string,
     *     description: string,
     *     items: array<int, array<string, mixed>>
     * } $category
     */
    private static function infolistTab(
        array $category
    ): Tab {
        return Tab::make(
            $category['label']
        )
            ->schema([
                self::infolistSection($category),
            ]);
    }

    /**
     * @param array{
     *     label: string,
     *     description: string,
     *     items: array<int, array<string, mixed>>
     * } $category
     */
    private static function formSection(
        array $category
    ): Section {
        return Section::make(
            $category['label']
        )
            ->description(
                $category['description']
            )
            ->columns(2)
            ->schema(
                collect($category['items'])
                    ->map(
                        fn (
                            array $definition
                        ): Placeholder => Placeholder::make(
                            'device_configuration_'
                            .self::componentKey(
                                $definition['key']
                            )
                        )
                            ->label(
                                $definition['label']
                            )
                            ->content(
                                fn (
                                    ?AccessDeviceRecord $record
                                ) => AccessDeviceConfigurationPresenter::render(
                                    $record,
                                    $definition
                                )
                            )
                            ->columnSpan(1)
                    )
                    ->all()
            )
            ->columnSpanFull();
    }

    /**
     * @param array{
     *     label: string,
     *     description: string,
     *     items: array<int, array<string, mixed>>
     * } $category
     */
    private static function infolistSection(
        array $category
    ): Section {
        return Section::make(
            $category['label']
        )
            ->description(
                $category['description']
            )
            ->columns(2)
            ->schema(
                collect($category['items'])
                    ->map(
                        fn (
                            array $definition
                        ): TextEntry => TextEntry::make(
                            'device_configuration_view_'
                            .self::componentKey(
                                $definition['key']
                            )
                        )
                            ->label(
                                $definition['label']
                            )
                            ->state(
                                fn (
                                    AccessDeviceRecord $record
                                ) => AccessDeviceConfigurationPresenter::render(
                                    $record,
                                    $definition
                                )
                            )
                            ->html()
                            ->columnSpan(1)
                    )
                    ->all()
            )
            ->columnSpanFull();
    }

    private static function componentKey(
        string $key
    ): string {
        return str_replace(
            ['.', '-'],
            '_',
            $key
        );
    }
}
