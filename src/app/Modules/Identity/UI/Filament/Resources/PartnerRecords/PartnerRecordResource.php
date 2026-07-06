<?php

namespace App\Modules\Identity\UI\Filament\Resources\PartnerRecords;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Identity\UI\Filament\Resources\PartnerRecords\Pages\ListPartnerRecords;
use App\Modules\Identity\UI\Filament\Resources\PartnerRecords\Schemas\PartnerRecordForm;
use App\Modules\Identity\UI\Filament\Resources\PartnerRecords\Schemas\PartnerRecordInfolist;
use App\Modules\Identity\UI\Filament\Resources\PartnerRecords\Tables\PartnerRecordsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PartnerRecordResource extends Resource
{
    protected static ?string $model = PartnerRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?string $navigationLabel = 'Parceiros';

    protected static ?string $modelLabel = 'parceiro';

    protected static ?string $pluralModelLabel = 'parceiros';

    protected static ?string $slug = 'partners';

    public static function form(Schema $schema): Schema
    {
        return PartnerRecordForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PartnerRecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PartnerRecordsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', PartnerRecord::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', PartnerRecord::class) ?? false;
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
        return auth()->user()?->can('deleteAny', PartnerRecord::class) ?? false;
    }

    public static function canRestore(Model $record): bool
    {
        return auth()->user()?->can('restore', $record) ?? false;
    }

    public static function canRestoreAny(): bool
    {
        return auth()->user()?->can('restoreAny', PartnerRecord::class) ?? false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return auth()->user()?->can('forceDelete', $record) ?? false;
    }

    public static function canForceDeleteAny(): bool
    {
        return auth()->user()?->can('forceDeleteAny', PartnerRecord::class) ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartnerRecords::route('/'),
        ];
    }
}
