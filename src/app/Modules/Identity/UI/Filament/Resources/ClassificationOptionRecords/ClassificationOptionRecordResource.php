<?php

namespace App\Modules\Identity\UI\Filament\Resources\ClassificationOptionRecords;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\ClassificationOptionRecord;
use App\Modules\Identity\UI\Filament\Resources\ClassificationOptionRecords\Pages\ListClassificationOptionRecords;
use App\Modules\Identity\UI\Filament\Resources\ClassificationOptionRecords\Schemas\ClassificationOptionRecordForm;
use App\Modules\Identity\UI\Filament\Resources\ClassificationOptionRecords\Schemas\ClassificationOptionRecordInfolist;
use App\Modules\Identity\UI\Filament\Resources\ClassificationOptionRecords\Tables\ClassificationOptionRecordsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ClassificationOptionRecordResource extends Resource
{
    protected static ?string $model = ClassificationOptionRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?string $navigationLabel = 'Classificações';

    protected static ?string $modelLabel = 'classificação';

    protected static ?string $pluralModelLabel = 'classificações';

    protected static ?string $slug = 'classifications';

    public static function form(Schema $schema): Schema
    {
        return ClassificationOptionRecordForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ClassificationOptionRecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClassificationOptionRecordsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', ClassificationOptionRecord::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', ClassificationOptionRecord::class) ?? false;
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
        return auth()->user()?->can('deleteAny', ClassificationOptionRecord::class) ?? false;
    }

    public static function canRestore(Model $record): bool
    {
        return auth()->user()?->can('restore', $record) ?? false;
    }

    public static function canRestoreAny(): bool
    {
        return auth()->user()?->can('restoreAny', ClassificationOptionRecord::class) ?? false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return auth()->user()?->can('forceDelete', $record) ?? false;
    }

    public static function canForceDeleteAny(): bool
    {
        return auth()->user()?->can('forceDeleteAny', ClassificationOptionRecord::class) ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClassificationOptionRecords::route('/'),
        ];
    }
}
