<?php

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeWorkScheduleTemplateRecords\Pages;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleTemplateRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Identity\UI\Filament\Actions\SelectCurrentTenantFirstAction;
use App\Modules\Identity\UI\Filament\Resources\EmployeeWorkScheduleTemplateRecords\EmployeeWorkScheduleTemplateRecordResource;
use App\Modules\Identity\UI\Filament\Resources\EmployeeWorkScheduleTemplateRecords\Schemas\EmployeeWorkScheduleTemplateRecordForm;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Validation\ValidationException;

class ListEmployeeWorkScheduleTemplateRecords extends ListRecords
{
    protected static string $resource = EmployeeWorkScheduleTemplateRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SelectCurrentTenantFirstAction::make(),

            CreateAction::make()
                ->label('Nova jornada')
                ->modalHeading('Nova jornada de trabalho')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalSubmitActionLabel('Salvar')
                ->createAnother(false)
                ->using(function (array $data): EmployeeWorkScheduleTemplateRecord {
                    $data['tenant_id'] = self::tenantIdForCreation($data);
                    $data['is_system'] = false;

                    $ruleGroups = $data['weekly_rule_groups'] ?? [];

                    $record = EmployeeWorkScheduleTemplateRecord::query()
                        ->create(
                            EmployeeWorkScheduleTemplateRecordForm::normalizeData(
                                $data
                            )
                        );

                    EmployeeWorkScheduleTemplateRecordForm::syncGeneratedDays(
                        $record,
                        $ruleGroups
                    );

                    return $record;
                })
                ->successNotificationTitle('Jornada criada'),
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
