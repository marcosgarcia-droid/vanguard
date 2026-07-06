<?php

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeRecords;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Pages\ListEmployeeRecords;
use App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Schemas\EmployeeRecordForm;
use App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Schemas\EmployeeRecordInfolist;
use App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Tables\EmployeeRecordsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EmployeeRecordResource extends Resource
{
    protected static ?string $model = EmployeeRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?string $slug = 'employees';

    public static function getNavigationLabel(): string
    {
        return 'Funcionários';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Cadastros';
    }

    public static function getModelLabel(): string
    {
        return 'funcionário';
    }

    public static function getPluralModelLabel(): string
    {
        return 'funcionários';
    }

    public static function form(Schema $schema): Schema
    {
        return EmployeeRecordForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeRecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeRecordsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeRecords::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', EmployeeRecord::class) ?? false;
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return ($user?->can('create', EmployeeRecord::class) ?? false)
            && app(TenantContext::class)->currentTenantIdForUser($user) !== null;
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
        return auth()->user()?->can('deleteAny', EmployeeRecord::class) ?? false;
    }

    public static function canRestore(Model $record): bool
    {
        return auth()->user()?->can('restore', $record) ?? false;
    }

    public static function canRestoreAny(): bool
    {
        return auth()->user()?->can('restoreAny', EmployeeRecord::class) ?? false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return auth()->user()?->can('forceDelete', $record) ?? false;
    }

    public static function canForceDeleteAny(): bool
    {
        return auth()->user()?->can('forceDeleteAny', EmployeeRecord::class) ?? false;
    }
}
