<?php

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeWorkScheduleTemplateRecords\Pages;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleTemplateRecord;
use App\Modules\Identity\UI\Filament\Resources\EmployeeWorkScheduleTemplateRecords\EmployeeWorkScheduleTemplateRecordResource;
use App\Modules\Identity\UI\Filament\Resources\EmployeeWorkScheduleTemplateRecords\Schemas\EmployeeWorkScheduleTemplateRecordForm;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListEmployeeWorkScheduleTemplateRecords extends ListRecords
{
    protected static string $resource = EmployeeWorkScheduleTemplateRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('selectCurrentTenantFirst')
                ->label('Selecione um grupo empresarial')
                ->color('gray')
                ->disabled()
                ->visible(fn (): bool => self::shouldShowSelectGroupAction()),
            CreateAction::make()
                ->label('Nova jornada')
                ->modalHeading('Nova jornada de trabalho')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalSubmitActionLabel('Salvar')
                ->createAnother(false)
                ->visible(fn (): bool => app(TenantContext::class)->currentTenantIdForUser(auth()->user()) !== null)
                ->using(function (array $data): EmployeeWorkScheduleTemplateRecord {
                    $data['tenant_id'] = app(TenantContext::class)->currentTenantIdForUser(auth()->user());
                    $data['is_system'] = false;

                    $ruleGroups = $data['weekly_rule_groups'] ?? [];
                    $record = EmployeeWorkScheduleTemplateRecord::query()->create(
                        EmployeeWorkScheduleTemplateRecordForm::normalizeData($data),
                    );

                    EmployeeWorkScheduleTemplateRecordForm::syncGeneratedDays($record, $ruleGroups);

                    return $record;
                })
                ->successNotificationTitle('Jornada criada'),
        ];
    }

    private static function shouldShowSelectGroupAction(): bool
    {
        $user = auth()->user();

        return $user?->hasRole(config('filament-shield.super_admin.name', 'super_admin')) === true
            && app(TenantContext::class)->currentTenantIdForUser($user) === null;
    }
}
