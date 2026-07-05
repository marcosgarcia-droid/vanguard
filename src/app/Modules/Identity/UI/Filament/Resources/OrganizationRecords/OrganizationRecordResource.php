<?php

namespace App\Modules\Identity\UI\Filament\Resources\OrganizationRecords;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Pages\ListOrganizationRecords;
use App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Schemas\OrganizationRecordForm;
use App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Schemas\OrganizationRecordInfolist;
use App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Tables\OrganizationRecordsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrganizationRecordResource extends Resource
{
    protected static ?string $model = OrganizationRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'legal_name';

    protected static ?string $slug = 'organizations';

    public static function getNavigationLabel(): string
    {
        return 'Organizações';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Identidade';
    }

    public static function getModelLabel(): string
    {
        return 'organização';
    }

    public static function getPluralModelLabel(): string
    {
        return 'organizações';
    }

    public static function form(Schema $schema): Schema
    {
        return OrganizationRecordForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrganizationRecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrganizationRecordsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganizationRecords::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return self::canManageOrganization('ViewAny');
    }

    public static function canView(Model $record): bool
    {
        return self::canManageOrganization('View');
    }

    public static function canCreate(): bool
    {
        return self::canManageOrganization('Create');
    }

    public static function canEdit(Model $record): bool
    {
        return self::canManageOrganization('Update');
    }

    public static function canDelete(Model $record): bool
    {
        return self::canManageOrganization('Delete');
    }

    public static function canDeleteAny(): bool
    {
        return self::canManageOrganization('DeleteAny');
    }

    public static function canRestore(Model $record): bool
    {
        return self::canManageOrganization('Restore');
    }

    public static function canRestoreAny(): bool
    {
        return self::canManageOrganization('RestoreAny');
    }

    public static function canForceDelete(Model $record): bool
    {
        return self::canManageOrganization('ForceDelete');
    }

    public static function canForceDeleteAny(): bool
    {
        return self::canManageOrganization('ForceDeleteAny');
    }

    private static function canManageOrganization(string $ability): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->hasRole(config('filament-shield.super_admin.name', 'super_admin'))) {
            return true;
        }

        return $user->can($ability.':OrganizationRecord');
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
