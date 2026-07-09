<?php

namespace App\Modules\Identity\UI\Http\Controllers;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChangeCurrentTenantController
{
    private const GLOBAL_TENANT_OPTION = '__global__';

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $tenantId = (string) $request->input('tenant_id');
        $context = app(TenantContext::class);

        if ($tenantId === self::GLOBAL_TENANT_OPTION) {
            if (! $user->hasRole(config('filament-shield.super_admin.name', 'super_admin'))) {
                Notification::make()
                    ->title('Troca de grupo não permitida')
                    ->body('Você não tem permissão para ativar a visão global.')
                    ->danger()
                    ->send();

                return back();
            }

            $context->clearSelectedTenant();

            Notification::make()
                ->title('Visão global ativada')
                ->body('Você voltou a visualizar todos os grupos empresariais.')
                ->success()
                ->send();

            return back();
        }

        $tenant = TenantRecord::query()->find($tenantId);

        if (! $tenant instanceof TenantRecord || ! $context->selectTenantForUser($user, $tenant)) {
            Notification::make()
                ->title('Grupo não selecionado')
                ->body('O grupo informado está inativo ou não está disponível para o seu usuário.')
                ->danger()
                ->send();

            return back();
        }

        Notification::make()
            ->title('Grupo ativo definido')
            ->body('Agora você está operando no grupo '.$tenant->name.'.')
            ->success()
            ->send();

        return back();
    }
}
