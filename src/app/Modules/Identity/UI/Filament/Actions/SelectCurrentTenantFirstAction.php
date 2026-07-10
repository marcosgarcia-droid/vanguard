<?php

namespace App\Modules\Identity\UI\Filament\Actions;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;
use Filament\Actions\Action;

class SelectCurrentTenantFirstAction
{
    public static function make(string $name = 'selectCurrentTenantFirst'): Action
    {
        return Action::make($name)
            ->label('Selecione um grupo empresarial')
            ->color('gray')
            ->disabled()
            ->visible(fn (): bool => self::shouldShow());
    }

    private static function shouldShow(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->hasRole(config('filament-shield.super_admin.name', 'super_admin'))
            && app(TenantContext::class)->currentTenantIdForUser($user) === null;
    }
}
