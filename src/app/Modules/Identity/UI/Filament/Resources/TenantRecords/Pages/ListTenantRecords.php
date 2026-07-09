<?php

namespace App\Modules\Identity\UI\Filament\Resources\TenantRecords\Pages;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\UI\Filament\Resources\TenantRecords\TenantRecordResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListTenantRecords extends ListRecords
{
    protected static string $resource = TenantRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clearCurrentTenant')
                ->label('Ver todos os grupos empresariais')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->hasRole(config('filament-shield.super_admin.name', 'super_admin'))
                    && app(TenantContext::class)->selectedTenantId() !== null)
                ->action(function (): void {
                    app(TenantContext::class)->clearSelectedTenant();

                    Notification::make()
                        ->title('Visualização global ativada')
                        ->body('Você voltou a visualizar todos os grupos empresariais.')
                        ->success()
                        ->send();
                }),

            CreateAction::make()
                ->label('Novo grupo empresarial')
                ->modalHeading('Novo grupo empresarial')
                ->modalSubmitActionLabel('Salvar')
                ->successNotificationTitle('Grupo empresarial cadastrado'),
        ];
    }
}
