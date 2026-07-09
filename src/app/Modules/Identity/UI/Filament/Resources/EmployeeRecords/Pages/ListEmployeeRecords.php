<?php

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Pages;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\EmployeeRecordResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListEmployeeRecords extends ListRecords
{
    protected static string $resource = EmployeeRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('selectCurrentTenantFirst')
                ->label('Selecione um grupo empresarial')
                ->color('gray')
                ->disabled()
                ->visible(fn (): bool => self::shouldShowSelectGroupAction()),
            CreateAction::make()
                ->label('Novo funcionário')
                ->modalHeading('Novo funcionário')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalSubmitActionLabel('Salvar')
                ->successNotificationTitle('Funcionário cadastrado'),
        ];
    }

    private static function shouldShowSelectGroupAction(): bool
    {
        $user = auth()->user();

        return $user?->hasRole(config('filament-shield.super_admin.name', 'super_admin')) === true
            && app(TenantContext::class)->currentTenantIdForUser($user) === null;
    }
}
