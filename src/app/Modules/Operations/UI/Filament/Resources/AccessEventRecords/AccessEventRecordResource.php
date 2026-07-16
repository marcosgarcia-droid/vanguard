<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessEventRecords;

use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Pages\ListAccessEventRecords;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Tables\AccessEventRecordsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AccessEventRecordResource extends Resource
{
    protected static ?string $model =
        AccessEventRecord::class;

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-arrows-right-left';

    protected static string|UnitEnum|null $navigationGroup =
        'Controle de acesso';

    protected static ?string $navigationLabel =
        'Eventos de acesso';

    protected static ?string $modelLabel =
        'evento de acesso';

    protected static ?string $pluralModelLabel =
        'eventos de acesso';

    protected static ?string $recordTitleAttribute =
        'external_event_id';

    protected static ?string $slug =
        'eventos-de-acesso';

    public static function table(Table $table): Table
    {
        return AccessEventRecordsTable::configure(
            $table
        );
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(
            'viewAny',
            AccessEventRecord::class
        ) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canView(
        Model $record
    ): bool {
        return auth()->user()?->can(
            'view',
            $record
        ) ?? false;
    }

    public static function canEdit(
        Model $record
    ): bool {
        return false;
    }

    public static function canDelete(
        Model $record
    ): bool {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canRestore(
        Model $record
    ): bool {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }

    public static function canForceDelete(
        Model $record
    ): bool {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccessEventRecords::route('/'),
        ];
    }
}
