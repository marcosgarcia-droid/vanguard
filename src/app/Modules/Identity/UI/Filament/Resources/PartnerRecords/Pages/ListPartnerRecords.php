<?php

namespace App\Modules\Identity\UI\Filament\Resources\PartnerRecords\Pages;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Identity\UI\Filament\Actions\ChangeCurrentTenantAction;
use App\Modules\Identity\UI\Filament\Resources\PartnerRecords\PartnerRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;

class ListPartnerRecords extends ListRecords
{
    protected static string $resource = PartnerRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ChangeCurrentTenantAction::make(PartnerRecordResource::getUrl()),

            CreateAction::make()
                ->label('Novo parceiro')
                ->modalHeading('Novo parceiro')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalSubmitActionLabel('Salvar')
                ->createAnother(false)
                ->visible(fn (): bool => app(TenantContext::class)->currentTenantIdForUser(auth()->user()) !== null)
                ->using(function (array $data): PartnerRecord {
                    $officialDocument = $data['official_document'] ?? null;

                    $data['tenant_id'] = app(TenantContext::class)->currentTenantIdForUser(auth()->user());
                    $data['person_type'] = PartnerRecord::personTypeFromOfficialDocument($officialDocument)
                        ?: ($data['person_type'] ?? 'individual');

                    unset($data['official_document']);

                    return DB::transaction(function () use ($data, $officialDocument): PartnerRecord {
                        $record = PartnerRecord::query()->create($data);
                        $record->syncOfficialDocument($officialDocument);

                        return $record->refresh();
                    });
                })
                ->successNotificationTitle('Parceiro criado'),
        ];
    }
}
