<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitRecords\Pages;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\AuthorizeVisitAction;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\CancelVisitAction;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\CheckInVisitAction;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\CheckOutVisitAction;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\RegisterVisitArrivalAction;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\RejectVisitAction;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\VisitRecordResource;
use App\Support\VanguardText;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Wezlo\FilamentKanban\Concerns\HasKanbanBoard;
use Wezlo\FilamentKanban\KanbanBoard;

class KanbanVisitRecords extends ListVisitRecords
{
    use HasKanbanBoard;

    protected static string $resource = VisitRecordResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->kanbanFilters = [
            'status' => null,
        ];
    }

    public function getBreadcrumb(): string
    {
        return 'Kanban';
    }

    public function getTabs(): array
    {
        return [];
    }

    public function applyKanbanFilters(): void
    {
        // A chamada sincroniza os campos deferidos e renderiza o Kanban.
    }

    public function clearKanbanFilters(): void
    {
        $this->kanbanFilters = [
            'status' => null,
        ];

        $this->kanbanFiltersForm->fill(
            $this->kanbanFilters
        );
    }

    public function kanban(KanbanBoard $kanban): KanbanBoard
    {
        return $kanban
            ->enumColumn('status', VisitStatus::class)
            ->excludeColumns([
                VisitStatus::Draft,
                VisitStatus::Rejected,
                VisitStatus::Cancelled,
                VisitStatus::Expired,
            ])
            ->boardView(
                'filament.resources.visit-records.kanban.board'
            )
            ->columnView(
                'filament.resources.visit-records.kanban.column'
            )
            ->cardView(
                'filament.resources.visit-records.kanban.card'
            )
            ->columnWidth('340px')
            ->searchable([
                'purpose',
                'visitor.full_name',
                'visitor.preferred_name',
                'organization.legal_name',
                'hostEmployee.full_name',
                'partner.name',
                'partner.trade_name',
            ])
            ->filters([
                Select::make('status')
                    ->label('Situação')
                    ->options([
                        VisitStatus::Scheduled->value => VisitStatus::Scheduled->label(),
                        VisitStatus::PendingAuthorization->value => VisitStatus::PendingAuthorization->label(),
                        VisitStatus::Authorized->value => VisitStatus::Authorized->label(),
                        VisitStatus::InProgress->value => VisitStatus::InProgress->label(),
                        VisitStatus::Completed->value => VisitStatus::Completed->label(),
                    ])
                    ->native(false),
            ])
            ->filtersColumns(1)
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $this->scopeKanbanQuery(
                    $query
                )
            )
            ->emptyState(
                'Nenhuma visita',
                'Não existem visitas nesta situação.',
                'heroicon-o-calendar-days'
            )
            ->canMove(fn (): bool => false);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('listView')
                ->label('Lista')
                ->tooltip('Visualizar como lista')
                ->icon('heroicon-o-list-bullet')
                ->url(
                    fn (): string => VisitRecordResource::getUrl(
                        'list'
                    )
                ),

            ...parent::getHeaderActions(),
        ];
    }

    public function viewVisitAction(): Action
    {
        return Action::make('viewVisit')
            ->label('Visualizar')
            ->tooltip('Visualizar visita')
            ->icon('heroicon-o-eye')
            ->iconButton()
            ->color('gray')
            ->modalHeading(
                fn (VisitRecord $record): string => 'Visualizar visita - '
                    .VanguardText::upper(
                        $record->visitor?->full_name
                            ?: 'VISITANTE'
                    )
            )
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->schema(
                fn (Schema $schema): Schema => VisitRecordResource::infolist(
                    $schema
                )
            )
            ->record(
                fn (array $arguments): VisitRecord => $this->visitFromArguments(
                    $arguments
                )
            )
            ->visible(
                fn (VisitRecord $record): bool => VisitRecordResource::canView(
                    $record
                )
            );
    }

    public function registerVisitArrivalAction(): Action
    {
        return RegisterVisitArrivalAction::make()
            ->record(
                fn (array $arguments): VisitRecord => $this->visitFromArguments(
                    $arguments
                )
            );
    }

    public function authorizeVisitAction(): Action
    {
        return AuthorizeVisitAction::make()
            ->record(
                fn (array $arguments): VisitRecord => $this->visitFromArguments(
                    $arguments
                )
            );
    }

    public function rejectVisitAction(): Action
    {
        return RejectVisitAction::make()
            ->record(
                fn (array $arguments): VisitRecord => $this->visitFromArguments(
                    $arguments
                )
            );
    }

    public function checkInVisitAction(): Action
    {
        return CheckInVisitAction::make()
            ->record(
                fn (array $arguments): VisitRecord => $this->visitFromArguments(
                    $arguments
                )
            );
    }

    public function checkOutVisitAction(): Action
    {
        return CheckOutVisitAction::make()
            ->record(
                fn (array $arguments): VisitRecord => $this->visitFromArguments(
                    $arguments
                )
            );
    }

    public function cancelVisitAction(): Action
    {
        return CancelVisitAction::make()
            ->record(
                fn (array $arguments): VisitRecord => $this->visitFromArguments(
                    $arguments
                )
            );
    }

    public function visitorPhotoUrl(
        VisitRecord $record
    ): ?string {
        $visitor = $record->visitor;

        if (
            blank($visitor?->photo_path)
            || blank($visitor?->photo_disk)
        ) {
            return null;
        }

        try {
            $storage = Storage::disk(
                $visitor->photo_disk ?: 'local'
            );

            if (! $storage->exists($visitor->photo_path)) {
                return null;
            }

            return $storage->temporaryUrl(
                $visitor->photo_path,
                now()
                    ->addMinutes(
                        config(
                            'filament.temporary_file_url_expiry_minutes',
                            30
                        )
                    )
                    ->endOfHour()
            );
        } catch (Throwable) {
            return null;
        }
    }

    public function visitorInitials(
        VisitRecord $record
    ): string {
        $name = trim(
            (string) (
                $record->visitor?->display_name
                ?: $record->visitor?->full_name
            )
        );

        if ($name === '') {
            return 'V';
        }

        return collect(
            preg_split('/\s+/', $name) ?: []
        )
            ->filter()
            ->take(2)
            ->map(
                fn (string $part): string => mb_strtoupper(
                    mb_substr($part, 0, 1)
                )
            )
            ->implode('');
    }

    private function scopeKanbanQuery(
        Builder $query
    ): Builder {
        $tenantContext = app(TenantContext::class);
        $user = auth()->user();

        $tenantContext->applyTenantScope(
            $query->with([
                'organization',
                'visitor',
                'hostEmployee',
                'partner',
            ]),
            $user
        );

        $tenantContext->applyUserOrganizationScope(
            $query,
            $user
        );

        return $query
            ->whereIn(
                'status',
                [
                    VisitStatus::Scheduled->value,
                    VisitStatus::PendingAuthorization->value,
                    VisitStatus::Authorized->value,
                    VisitStatus::InProgress->value,
                    VisitStatus::Completed->value,
                ]
            )
            ->orderBy('expected_start_at');
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function visitFromArguments(
        array $arguments
    ): VisitRecord {
        $query = VisitRecord::query()
            ->with([
                'organization',
                'visitor',
                'hostEmployee',
                'partner',
            ])
            ->whereKey(
                (string) ($arguments['record'] ?? '')
            );

        $tenantContext = app(TenantContext::class);
        $user = auth()->user();

        $tenantContext->applyTenantScope(
            $query,
            $user
        );

        $tenantContext->applyUserOrganizationScope(
            $query,
            $user
        );

        /** @var VisitRecord $record */
        $record = $query->firstOrFail();

        abort_unless(
            VisitRecordResource::canView($record),
            403
        );

        return $record;
    }
}
