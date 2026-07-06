<?php

namespace App\Modules\Identity\UI\Filament\Resources\PartnerRecords\Pages;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\UI\Filament\Resources\PartnerRecords\PartnerRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListPartnerRecords extends ListRecords
{
    protected static string $resource = PartnerRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Novo parceiro')
                ->modalHeading('Novo parceiro')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalSubmitActionLabel('Salvar')
                ->createAnother(false)
                ->visible(fn (): bool => app(TenantContext::class)->currentTenantIdForUser(auth()->user()) !== null)
                ->mutateDataUsing(function (array $data): array {
                    $data['tenant_id'] = app(TenantContext::class)->currentTenantIdForUser(auth()->user());

                    return $data;
                })
                ->successNotificationTitle('Parceiro criado'),
        ];
    }
}
