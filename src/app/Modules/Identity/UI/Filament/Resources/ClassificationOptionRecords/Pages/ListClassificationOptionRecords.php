<?php

namespace App\Modules\Identity\UI\Filament\Resources\ClassificationOptionRecords\Pages;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\UI\Filament\Resources\ClassificationOptionRecords\ClassificationOptionRecordResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListClassificationOptionRecords extends ListRecords
{
    protected static string $resource = ClassificationOptionRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('selectCurrentTenantFirst')
                ->label('Selecione um grupo empresarial')
                ->color('gray')
                ->disabled()
                ->visible(fn (): bool => self::shouldShowSelectGroupAction()),
            CreateAction::make()
                ->label('Nova classificação')
                ->modalHeading('Nova classificação')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalSubmitActionLabel('Salvar')
                ->createAnother(false)
                ->visible(fn (): bool => app(TenantContext::class)->currentTenantIdForUser(auth()->user()) !== null)
                ->mutateDataUsing(function (array $data): array {
                    $data['tenant_id'] = app(TenantContext::class)->currentTenantIdForUser(auth()->user());
                    $data['is_system'] = false;

                    return $data;
                })
                ->successNotificationTitle('Classificação criada'),
        ];
    }

    private static function shouldShowSelectGroupAction(): bool
    {
        $user = auth()->user();

        return $user?->hasRole(config('filament-shield.super_admin.name', 'super_admin')) === true
            && app(TenantContext::class)->currentTenantIdForUser($user) === null;
    }
}
