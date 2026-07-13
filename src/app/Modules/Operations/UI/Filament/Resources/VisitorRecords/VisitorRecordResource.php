<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Pages\ListVisitorRecords;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Schemas\VisitorRecordForm;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Schemas\VisitorRecordInfolist;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Tables\VisitorRecordsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class VisitorRecordResource extends Resource
{
    protected static ?string $model = VisitorRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?string $navigationLabel = 'Visitantes';

    protected static ?string $modelLabel = 'visitante';

    protected static ?string $pluralModelLabel = 'visitantes';

    protected static ?string $slug = 'visitors';

    public static function form(Schema $schema): Schema
    {
        return VisitorRecordForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VisitorRecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VisitorRecordsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', VisitorRecord::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', VisitorRecord::class) ?? false;
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
        return auth()->user()?->can('deleteAny', VisitorRecord::class) ?? false;
    }

    public static function canRestore(Model $record): bool
    {
        return auth()->user()?->can('restore', $record) ?? false;
    }

    public static function canRestoreAny(): bool
    {
        return auth()->user()?->can('restoreAny', VisitorRecord::class) ?? false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return auth()->user()?->can('forceDelete', $record) ?? false;
    }

    public static function canForceDeleteAny(): bool
    {
        return auth()->user()?->can('forceDeleteAny', VisitorRecord::class) ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVisitorRecords::route('/'),
        ];
    }
}
