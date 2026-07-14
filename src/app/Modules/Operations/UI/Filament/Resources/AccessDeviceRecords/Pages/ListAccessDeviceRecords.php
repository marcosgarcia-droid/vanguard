<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessDeviceRecords\Pages;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Operations\UI\Filament\Resources\AccessDeviceRecords\AccessDeviceRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Validation\ValidationException;

class ListAccessDeviceRecords extends ListRecords
{
    protected static string $resource =
        AccessDeviceRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Novo dispositivo')
                ->modalHeading('Novo dispositivo de acesso')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalSubmitActionLabel('Salvar')
                ->createAnother(false)
                ->mutateDataUsing(
                    function (array $data): array {
                        $organization =
                            self::organizationForCreation(
                                $data['organization_id'] ?? null
                            );

                        $data['tenant_id'] =
                            $organization->tenant_id;

                        $data['device_type'] =
                            $data['device_type']
                                ?? 'facial_reader';

                        return $data;
                    }
                )
                ->successNotificationTitle(
                    'Dispositivo cadastrado'
                ),
        ];
    }

    private static function organizationForCreation(
        ?string $organizationId
    ): OrganizationRecord {
        if (blank($organizationId)) {
            throw ValidationException::withMessages([
                'organization_id' => 'Selecione a unidade do dispositivo.',
            ]);
        }

        $organization = OrganizationRecord::query()
            ->whereKey($organizationId)
            ->where('status', 'active')
            ->first();

        if (! $organization instanceof OrganizationRecord) {
            throw ValidationException::withMessages([
                'organization_id' => 'A unidade selecionada não está disponível.',
            ]);
        }

        $tenantContext = app(TenantContext::class);
        $user = auth()->user();

        if (! $tenantContext->hasOrganizationAccess(
            $user,
            $organization->id
        )) {
            throw ValidationException::withMessages([
                'organization_id' => 'Você não possui acesso à unidade selecionada.',
            ]);
        }

        $currentTenantId =
            $tenantContext->currentTenantIdForUser($user);

        if (
            filled($currentTenantId)
            && $currentTenantId !== $organization->tenant_id
        ) {
            throw ValidationException::withMessages([
                'organization_id' => 'A unidade não pertence ao grupo empresarial selecionado.',
            ]);
        }

        return $organization;
    }
}
