<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessDeviceRecords\Schemas;

use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\AccessDeviceConfigurationCatalog;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\AccessDeviceConfigurationPresenter;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use Filament\Forms\Components\Placeholder;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;

final class AccessDeviceConfigurationSchema
{
    /**
     * @return array<int, Section>
     */
    public static function formSections(): array
    {
        return collect(
            AccessDeviceConfigurationCatalog::grouped()
        )
            ->map(
                fn (array $category): Section => Section::make($category['label'])
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
                    ->columnSpanFull()
            )
            ->values()
            ->all();
    }

    /**
     * @return array<int, Section>
     */
    public static function infolistSections(): array
    {
        return collect(
            AccessDeviceConfigurationCatalog::grouped()
        )
            ->map(
                fn (array $category): Section => Section::make($category['label'])
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
                    ->columnSpanFull()
            )
            ->values()
            ->all();
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
