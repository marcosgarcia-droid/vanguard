<?php

namespace App\Modules\Identity\UI\Filament\Resources\TenantRecords;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Identity\UI\Filament\Resources\TenantRecords\Pages\ListTenantRecords;
use App\Modules\Identity\UI\Filament\Resources\TenantRecords\Schemas\TenantRecordForm;
use App\Modules\Identity\UI\Filament\Resources\TenantRecords\Schemas\TenantRecordInfolist;
use App\Modules\Identity\UI\Filament\Resources\TenantRecords\Tables\TenantRecordsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TenantRecordResource extends Resource
{
    protected static ?string $model = TenantRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'tenants';

    public static function getNavigationLabel(): string
    {
        return 'Tenants';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Acesso';
    }

    public static function getModelLabel(): string
    {
        return 'tenant';
    }

    public static function getPluralModelLabel(): string
    {
        return 'tenants';
    }

    public static function form(Schema $schema): Schema
    {
        return TenantRecordForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TenantRecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TenantRecordsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenantRecords::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', TenantRecord::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', TenantRecord::class) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
