<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitRecords\Pages;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Identity\UI\Filament\Actions\SelectCurrentTenantFirstAction;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Tables\VisitRecordsTable;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\VisitRecordResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ListVisitRecords extends ListRecords
{
    protected static string $resource = VisitRecordResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todas'),

            'today' => Tab::make('Hoje')
                ->modifyQueryUsing(
                    fn (
                        Builder $query
                    ): Builder => VisitRecordsTable::applyTodayFilter(
                        $query
                    )
                ),

            'pending_authorization' => Tab::make(
                'Aguardando autorização'
            )
                ->modifyQueryUsing(
                    fn (
                        Builder $query
                    ): Builder => VisitRecordsTable::applyStatusFilter(
                        $query,
                        VisitStatus::PendingAuthorization->value
                    )
                ),

            'authorized' => Tab::make('Autorizadas')
                ->modifyQueryUsing(
                    fn (
                        Builder $query
                    ): Builder => VisitRecordsTable::applyStatusFilter(
                        $query,
                        VisitStatus::Authorized->value
                    )
                ),

            'in_progress' => Tab::make('Em andamento')
                ->modifyQueryUsing(
                    fn (
                        Builder $query
                    ): Builder => VisitRecordsTable::applyStatusFilter(
                        $query,
                        VisitStatus::InProgress->value
                    )
                ),

            'completed' => Tab::make('Concluídas')
                ->modifyQueryUsing(
                    fn (
                        Builder $query
                    ): Builder => VisitRecordsTable::applyStatusFilter(
                        $query,
                        VisitStatus::Completed->value
                    )
                ),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            SelectCurrentTenantFirstAction::make(),

            Action::make('kanbanView')
                ->label('Kanban')
                ->tooltip('Visualizar como Kanban')
                ->icon('heroicon-o-view-columns')
                ->url(
                    fn (): string => VisitRecordResource::getUrl(
                        'index'
                    )
                )
                ->visible(
                    fn (): bool => static::class === self::class
                ),

            CreateAction::make()
                ->label('Nova visita')
                ->modalHeading('Agendar nova visita')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalSubmitActionLabel('Agendar visita')
                ->createAnother(false)
                ->mutateDataUsing(
                    fn (array $data): array => self::validatedCreationData(
                        $data
                    )
                )
                ->successNotificationTitle('Visita agendada'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function validatedCreationData(array $data): array
    {
        $organization = self::organizationForCreation(
            $data['organization_id'] ?? null
        );

        self::validateVisitor(
            $data['visitor_id'] ?? null,
            $organization
        );

        self::validateEmployee(
            $data['host_employee_id'] ?? null,
            $organization
        );

        self::validatePartner(
            $data['partner_id'] ?? null,
            $organization
        );

        $data['tenant_id'] = $organization->tenant_id;
        $data['status'] = VisitStatus::Scheduled->value;

        return $data;
    }

    private static function organizationForCreation(
        ?string $organizationId
    ): OrganizationRecord {
        if (blank($organizationId)) {
            throw ValidationException::withMessages([
                'organization_id' => 'Selecione a unidade da visita.',
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

        if (! $tenantContext->hasOrganizationAccess($user, $organization->id)) {
            throw ValidationException::withMessages([
                'organization_id' => 'Você não possui acesso à unidade selecionada.',
            ]);
        }

        $currentTenantId = $tenantContext->currentTenantIdForUser($user);

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

    private static function validateVisitor(
        ?string $visitorId,
        OrganizationRecord $organization
    ): void {
        if (blank($visitorId)) {
            throw ValidationException::withMessages([
                'visitor_id' => 'Selecione o visitante.',
            ]);
        }

        $exists = VisitorRecord::query()
            ->whereKey($visitorId)
            ->where('tenant_id', $organization->tenant_id)
            ->where('organization_id', $organization->id)
            ->where('status', VisitorStatus::Active)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'visitor_id' => 'O visitante selecionado não está disponível para esta unidade.',
            ]);
        }
    }

    private static function validateEmployee(
        ?string $employeeId,
        OrganizationRecord $organization
    ): void {
        if (blank($employeeId)) {
            return;
        }

        $exists = EmployeeRecord::query()
            ->whereKey($employeeId)
            ->where('tenant_id', $organization->tenant_id)
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'host_employee_id' => 'O visitado selecionado não está disponível para esta unidade.',
            ]);
        }
    }

    private static function validatePartner(
        ?string $partnerId,
        OrganizationRecord $organization
    ): void {
        if (blank($partnerId)) {
            return;
        }

        $exists = PartnerRecord::query()
            ->whereKey($partnerId)
            ->where('tenant_id', $organization->tenant_id)
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'partner_id' => 'O parceiro selecionado não está disponível para esta unidade.',
            ]);
        }
    }
}
