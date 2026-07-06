<?php

namespace App\Modules\Identity\UI\Filament\Resources\ClassificationOptionRecords\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ClassificationOptionRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                Tabs::make('Visualização da classificação')
                    ->id('classification-option-infolist-tabs')
                    ->persistTab()
                    ->tabs([
                        Tab::make('Classificação')
                            ->schema([
                                Section::make('Dados principais')
                                    ->columns(6)
                                    ->schema([
                                        TextEntry::make('category_display')
                                            ->label('Categoria')
                                            ->columnSpan(3),

                                        TextEntry::make('status_display')
                                            ->label('Status')
                                            ->badge()
                                            ->columnSpan(1),

                                        TextEntry::make('sort_order')
                                            ->label('Ordem')
                                            ->columnSpan(1),

                                        IconEntry::make('is_system')
                                            ->label('Padrão do sistema')
                                            ->boolean()
                                            ->columnSpan(1),

                                        TextEntry::make('name')
                                            ->label('Nome')
                                            ->columnSpan(3),

                                        TextEntry::make('code')
                                            ->label('Código interno')
                                            ->columnSpan(3),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Descrição')
                            ->schema([
                                Section::make('Descrição')
                                    ->schema([
                                        TextEntry::make('description')
                                            ->label('Descrição')
                                            ->placeholder('Nenhuma descrição cadastrada')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
