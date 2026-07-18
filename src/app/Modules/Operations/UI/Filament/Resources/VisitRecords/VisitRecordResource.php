<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitRecords;

use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Pages\KanbanVisitRecords;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Pages\ListVisitRecords;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Schemas\VisitRecordForm;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Schemas\VisitRecordInfolist;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Tables\VisitRecordsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class VisitRecordResource extends Resource
{
    protected static ?string $model = VisitRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Operações';

    protected static ?string $navigationLabel = 'Visitas';

    protected static ?string $modelLabel = 'visita';

    protected static ?string $pluralModelLabel = 'visitas';

    protected static ?string $slug = 'visits';

    public static function form(Schema $schema): Schema
    {
        return VisitRecordForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VisitRecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VisitRecordsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', VisitRecord::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(
            'create',
            VisitRecord::class
        ) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can('view', $record) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => KanbanVisitRecords::route('/'),
            'list' => ListVisitRecords::route('/list'),
        ];
    }
}
