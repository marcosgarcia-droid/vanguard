<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitRecords\Pages;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Identity\UI\Filament\Actions\SelectCurrentTenantFirstAction;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitVehicleRecord;
use App\Modules\Operations\Support\VehicleCatalog;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Tables\VisitRecordsTable;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\VisitRecordResource;
use App\Modules\Operations\UI\Notifications\VisitHostNotifier;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

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

            $this->scheduleVisitAction(),

            $this->registerVisitorAction(),
        ];
    }

    private function scheduleVisitAction(): CreateAction
    {
        return CreateAction::make()
            ->label('Agendar visita')
            ->tooltip('Agendar uma visita')
            ->icon('heroicon-o-calendar-days')
            ->modalHeading('Agendar nova visita')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel('Agendar visita')
            ->createAnother(false)
            ->mutateDataUsing(
                fn (array $data): array => $this
                    ->validatedCreationDataWithFeedback($data)
            )
            ->using(
                fn (
                    array $data
                ): VisitRecord => self::createVisitWithVehicle(
                    $data
                )
            )
            ->successNotificationTitle('Visita agendada')
            ->after(function (VisitRecord $record): void {
                try {
                    app(VisitHostNotifier::class)
                        ->notifyScheduled($record);
                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->title(
                            'Visita agendada, mas o aviso ao visitado não foi enviado'
                        )
                        ->body(
                            'A visita foi salva normalmente. O aviso poderá ser consultado e tratado separadamente.'
                        )
                        ->warning()
                        ->send();
                }
            });
    }

    private function registerVisitorAction(): CreateAction
    {
        return CreateAction::make('registerVisitor')
            ->label('Registrar visitante')
            ->tooltip('Registrar visitante na portaria')
            ->icon('heroicon-o-map-pin')
            ->color('warning')
            ->modalHeading('Registrar visitante na portaria')
            ->modalDescription(
                'Ao concluir, você confirma que o visitante está presente na portaria e que sua identidade foi conferida.'
            )
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel('Registrar visitante')
            ->createAnother(false)
            ->fillForm(
                fn (): array => [
                    'status' => VisitStatus::PendingAuthorization->value,
                    'expected_start_at' => now(),
                ]
            )
            ->visible(
                fn (): bool => auth()->user()?->can(
                    'registerAtGatehouse',
                    VisitRecord::class
                ) ?? false
            )
            ->mutateDataUsing(
                fn (array $data): array => $this
                    ->validatedGatehouseCreationDataWithFeedback(
                        $data
                    )
            )
            ->using(function (array $data): VisitRecord {
                Gate::authorize(
                    'registerAtGatehouse',
                    VisitRecord::class
                );

                return self::createVisitWithVehicle($data);
            })
            ->successNotificationTitle('Visitante registrado')
            ->after(function (VisitRecord $record): void {
                try {
                    app(VisitHostNotifier::class)
                        ->notifyArrival($record);
                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->title(
                            'Visitante registrado, mas o aviso ao visitado não foi enviado'
                        )
                        ->body(
                            'O visitante e sua chegada permanecem registrados normalmente.'
                        )
                        ->warning()
                        ->send();
                }
            });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validatedGatehouseCreationDataWithFeedback(
        array $data
    ): array {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'organization_id' => 'Não foi possível identificar o operador da portaria.',
            ]);
        }

        $data = $this->validatedCreationDataWithFeedback($data);
        $arrivedAt = now();

        $data['status'] = VisitStatus::PendingAuthorization->value;
        $data['expected_start_at'] = $arrivedAt;
        $data['arrived_by'] = (int) $user->id;
        $data['arrived_at'] = $arrivedAt;
        $data['identity_verified_by'] = (int) $user->id;
        $data['identity_verified_at'] = $arrivedAt;

        return $data;
    }

    protected function onValidationError(
        ValidationException $exception
    ): void {
        parent::onValidationError($exception);

        $this->notifyVisitFormValidationError();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validatedCreationDataWithFeedback(array $data): array
    {
        try {
            return self::validatedCreationData($data);
        } catch (ValidationException $exception) {
            $exception = $this
                ->visitFormValidationException($exception);

            $this->notifyVisitFormValidationError();

            $this->dispatch(
                'form-validation-error',
                livewireId: $this->getId()
            );

            throw $exception;
        }
    }

    private function visitFormValidationException(
        ValidationException $exception
    ): ValidationException {
        $statePath = $this
            ->getMountedActionSchema()
            ?->getStatePath();

        $errors = [];

        foreach ($exception->errors() as $field => $messages) {
            $errorKey = filled($statePath)
                ? "{$statePath}.{$field}"
                : $field;

            $errors[$errorKey] = $messages;
        }

        return ValidationException::withMessages(
            $errors
        );
    }

    private function notifyVisitFormValidationError(): void
    {
        Notification::make()
            ->danger()
            ->title('Revise os campos destacados antes de continuar.')
            ->send();
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

        self::validateVehicleData($data);

        $vehicleSelections = self::resolvedVehicleSelections($data);

        $data['vehicle_brand'] = $vehicleSelections['brand'];
        $data['vehicle_model'] = $vehicleSelections['model'];
        $data['vehicle_color'] = $vehicleSelections['color'];

        $data['tenant_id'] = $organization->tenant_id;
        $data['status'] = VisitStatus::Scheduled->value;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function validateVehicleData(
        array $data
    ): void {
        $plate = VisitVehicleRecord::normalizePlate(
            $data['vehicle_plate'] ?? null
        );

        $brandSelection = trim(
            (string) ($data['vehicle_brand'] ?? '')
        );

        $modelSelection = trim(
            (string) ($data['vehicle_model'] ?? '')
        );

        $colorSelection = trim(
            (string) ($data['vehicle_color'] ?? '')
        );

        $resolved = self::resolvedVehicleSelections($data);

        $hasVehicleData = filled($plate)
            || filled($resolved['brand'])
            || filled($resolved['model'])
            || filled($resolved['color'])
            || filled($data['vehicle_brand_other'] ?? null)
            || filled($data['vehicle_model_other'] ?? null)
            || filled($data['vehicle_color_other'] ?? null);

        if (! $hasVehicleData) {
            return;
        }

        if (
            blank($plate)
            || preg_match(
                '/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/',
                $plate
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                'vehicle_plate' => 'Informe uma placa válida no padrão antigo ou Mercosul.',
            ]);
        }

        if (
            filled($brandSelection)
            && $brandSelection !== VehicleCatalog::OTHER
            && ! VehicleCatalog::hasBrand($brandSelection)
        ) {
            throw ValidationException::withMessages([
                'vehicle_brand' => 'Selecione uma marca disponível no catálogo ou utilize Outra marca.',
            ]);
        }

        if (
            $brandSelection === VehicleCatalog::OTHER
            && blank($resolved['brand'])
        ) {
            throw ValidationException::withMessages([
                'vehicle_brand_other' => 'Informe a outra marca do veículo.',
            ]);
        }

        if (
            $brandSelection === VehicleCatalog::OTHER
            && blank($resolved['model'])
        ) {
            throw ValidationException::withMessages([
                'vehicle_model_other' => 'Informe o modelo do veículo.',
            ]);
        }

        if (
            filled($modelSelection)
            && $modelSelection !== VehicleCatalog::OTHER
            && filled($brandSelection)
            && $brandSelection !== VehicleCatalog::OTHER
            && ! VehicleCatalog::hasModel(
                $brandSelection,
                $modelSelection
            )
        ) {
            throw ValidationException::withMessages([
                'vehicle_model' => 'O modelo selecionado não pertence à marca informada.',
            ]);
        }

        if (
            $modelSelection === VehicleCatalog::OTHER
            && blank($resolved['model'])
        ) {
            throw ValidationException::withMessages([
                'vehicle_model_other' => 'Informe o outro modelo do veículo.',
            ]);
        }

        if (
            $colorSelection === VehicleCatalog::OTHER
            && blank($resolved['color'])
        ) {
            throw ValidationException::withMessages([
                'vehicle_color_other' => 'Informe a outra cor do veículo.',
            ]);
        }

    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{brand: ?string, model: ?string, color: ?string}
     */
    private static function resolvedVehicleSelections(
        array $data
    ): array {
        $brandSelection = $data['vehicle_brand'] ?? null;

        $brand = VehicleCatalog::resolveSelection(
            $brandSelection,
            $data['vehicle_brand_other'] ?? null
        );

        $model = $brandSelection === VehicleCatalog::OTHER
            ? self::nullableTrimmedValue(
                $data['vehicle_model_other'] ?? null
            )
            : VehicleCatalog::resolveSelection(
                $data['vehicle_model'] ?? null,
                $data['vehicle_model_other'] ?? null
            );

        $color = VehicleCatalog::resolveSelection(
            $data['vehicle_color'] ?? null,
            $data['vehicle_color_other'] ?? null
        );

        return [
            'brand' => $brand,
            'model' => $model,
            'color' => $color,
        ];
    }

    private static function nullableTrimmedValue(
        mixed $value
    ): ?string {
        $normalized = trim((string) $value);

        return $normalized !== ''
            ? $normalized
            : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function createVisitWithVehicle(
        array $data
    ): VisitRecord {

        $vehicleData = [
            'plate' => VisitVehicleRecord::normalizePlate(
                $data['vehicle_plate'] ?? null
            ),
            'brand' => $data['vehicle_brand'] ?? null,
            'model' => $data['vehicle_model'] ?? null,
            'color' => $data['vehicle_color'] ?? null,
            'entry_authorized' => false,
            'entry_authorized_by' => null,
            'entry_authorized_at' => null,
        ];

        foreach ([
            'vehicle_plate',
            'vehicle_brand',
            'vehicle_brand_other',
            'vehicle_model',
            'vehicle_model_other',
            'vehicle_color',
            'vehicle_color_other',
            'vehicle_entry_authorized',
        ] as $field) {
            unset($data[$field]);
        }

        return DB::transaction(
            function () use (
                $data,
                $vehicleData
            ): VisitRecord {
                $visit = VisitRecord::query()->create(
                    $data
                );

                if (filled($vehicleData['plate'])) {
                    $visit->vehicle()->create(
                        $vehicleData
                    );
                }

                return $visit;
            }
        );
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
            ->where('status', 'active')
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'host_employee_id' => 'O visitado selecionado não está disponível para este grupo empresarial.',
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
