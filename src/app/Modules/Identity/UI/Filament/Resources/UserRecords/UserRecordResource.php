<?php

namespace App\Modules\Identity\UI\Filament\Resources\UserRecords;

use App\Models\User;
use App\Modules\Identity\UI\Filament\Resources\UserRecords\Pages\ListUserRecords;
use App\Modules\Identity\UI\Filament\Resources\UserRecords\Schemas\UserRecordForm;
use App\Modules\Identity\UI\Filament\Resources\UserRecords\Tables\UserRecordsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserRecordResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'users';

    public static function getNavigationLabel(): string
    {
        return 'Usuários';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Acesso';
    }

    public static function getModelLabel(): string
    {
        return 'usuário';
    }

    public static function getPluralModelLabel(): string
    {
        return 'usuários';
    }

    public static function form(Schema $schema): Schema
    {
        return UserRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserRecordsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserRecords::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }
}
