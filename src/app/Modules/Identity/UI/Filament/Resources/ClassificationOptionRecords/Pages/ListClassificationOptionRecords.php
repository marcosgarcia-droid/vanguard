<?php

namespace App\Modules\Identity\UI\Filament\Resources\ClassificationOptionRecords\Pages;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Identity\UI\Filament\Actions\SelectCurrentTenantFirstAction;
use App\Modules\Identity\UI\Filament\Resources\ClassificationOptionRecords\ClassificationOptionRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Validation\ValidationException;

class ListClassificationOptionRecords extends ListRecords
{
    protected static string $resource = ClassificationOptionRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SelectCurrentTenantFirstAction::make(),

            CreateAction::make()
                ->label('Nova classificação')
                ->modalHeading('Nova classificação')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalSubmitActionLabel('Salvar')
                ->createAnother(false)
                ->mutateDataUsing(function (array $data): array {
                    $data['tenant_id'] = self::tenantIdForCreation($data);
                    $data['is_system'] = false;

                    return $data;
                })
                ->successNotificationTitle('Classificação criada'),
        ];
    }

    private static function tenantIdForCreation(array $data): string
    {
        $tenantContext = app(TenantContext::class);
        $user = auth()->user();

        $tenantId = filled($data['tenant_id'] ?? null)
            ? (string) $data['tenant_id']
            : $tenantContext->currentTenantIdForUser($user);

        $tenant = filled($tenantId)
            ? TenantRecord::query()
                ->where('status', 'active')
                ->find($tenantId)
            : null;

        if (
            ! $tenant instanceof TenantRecord
            || ! $tenantContext->canSelectTenant($user, $tenant)
        ) {
            throw ValidationException::withMessages([
                'tenant_id' => 'Selecione um grupo empresarial válido.',
            ]);
        }

        return $tenant->id;
    }
}
