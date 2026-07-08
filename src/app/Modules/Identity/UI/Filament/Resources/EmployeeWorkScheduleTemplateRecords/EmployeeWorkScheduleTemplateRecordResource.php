<?php

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeWorkScheduleTemplateRecords;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleTemplateRecord;
use App\Modules\Identity\UI\Filament\Resources\EmployeeWorkScheduleTemplateRecords\Pages\ListEmployeeWorkScheduleTemplateRecords;
use App\Modules\Identity\UI\Filament\Resources\EmployeeWorkScheduleTemplateRecords\Schemas\EmployeeWorkScheduleTemplateRecordForm;
use App\Modules\Identity\UI\Filament\Resources\EmployeeWorkScheduleTemplateRecords\Schemas\EmployeeWorkScheduleTemplateRecordInfolist;
use App\Modules\Identity\UI\Filament\Resources\EmployeeWorkScheduleTemplateRecords\Tables\EmployeeWorkScheduleTemplateRecordsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class EmployeeWorkScheduleTemplateRecordResource extends Resource
{
    protected static ?string $model = EmployeeWorkScheduleTemplateRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?string $navigationLabel = 'Jornadas de trabalho';

    protected static ?string $modelLabel = 'jornada de trabalho';

    protected static ?string $pluralModelLabel = 'jornadas de trabalho';

    protected static ?string $slug = 'work-schedules';

    public static function form(Schema $schema): Schema
    {
        return EmployeeWorkScheduleTemplateRecordForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeWorkScheduleTemplateRecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeWorkScheduleTemplateRecordsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', EmployeeWorkScheduleTemplateRecord::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', EmployeeWorkScheduleTemplateRecord::class) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can('view', $record) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('deleteAny', EmployeeWorkScheduleTemplateRecord::class) ?? false;
    }

    public static function canRestore(Model $record): bool
    {
        return auth()->user()?->can('restore', $record) ?? false;
    }

    public static function canRestoreAny(): bool
    {
        return auth()->user()?->can('restoreAny', EmployeeWorkScheduleTemplateRecord::class) ?? false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return auth()->user()?->can('forceDelete', $record) ?? false;
    }

    public static function canForceDeleteAny(): bool
    {
        return auth()->user()?->can('forceDeleteAny', EmployeeWorkScheduleTemplateRecord::class) ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeWorkScheduleTemplateRecords::route('/'),
        ];
    }
}
