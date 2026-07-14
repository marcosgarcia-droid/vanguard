<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessDeviceRecords;

use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\UI\Filament\Resources\AccessDeviceRecords\Pages\ListAccessDeviceRecords;
use App\Modules\Operations\UI\Filament\Resources\AccessDeviceRecords\Schemas\AccessDeviceRecordForm;
use App\Modules\Operations\UI\Filament\Resources\AccessDeviceRecords\Schemas\AccessDeviceRecordInfolist;
use App\Modules\Operations\UI\Filament\Resources\AccessDeviceRecords\Tables\AccessDeviceRecordsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AccessDeviceRecordResource extends Resource
{
    protected static ?string $model =
        AccessDeviceRecord::class;

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-cpu-chip';

    protected static string|UnitEnum|null $navigationGroup =
        'Controle de acesso';

    protected static ?string $navigationLabel =
        'Dispositivos';

    protected static ?string $modelLabel =
        'dispositivo de acesso';

    protected static ?string $pluralModelLabel =
        'dispositivos de acesso';

    protected static ?string $recordTitleAttribute =
        'name';

    protected static ?string $slug =
        'dispositivos-de-acesso';

    public static function form(Schema $schema): Schema
    {
        return AccessDeviceRecordForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AccessDeviceRecordInfolist::configure(
            $schema
        );
    }

    public static function table(Table $table): Table
    {
        return AccessDeviceRecordsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(
            'viewAny',
            AccessDeviceRecord::class
        ) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(
            'create',
            AccessDeviceRecord::class
        ) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can(
            'view',
            $record
        ) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can(
            'update',
            $record
        ) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccessDeviceRecords::route('/'),
        ];
    }
}
