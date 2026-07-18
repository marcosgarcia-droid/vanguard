@php
    use App\Modules\Operations\Domain\Visits\VisitStatus;

    $status = VisitStatus::tryFrom($column->value);

    $label = $status?->label() ?: $column->label;

    $color = match ($status) {
        VisitStatus::Scheduled => 'info',
        VisitStatus::PendingAuthorization => 'warning',
        VisitStatus::Authorized => 'success',
        VisitStatus::InProgress => 'primary',
        VisitStatus::Completed => 'success',
        default => 'gray',
    };

    $colorClasses = match ($color) {
        'primary' => 'bg-primary-50 border-primary-200 dark:bg-primary-400/10 dark:border-primary-400/20',
        'success' => 'bg-success-50 border-success-200 dark:bg-success-400/10 dark:border-success-400/20',
        'warning' => 'bg-warning-50 border-warning-200 dark:bg-warning-400/10 dark:border-warning-400/20',
        'info' => 'bg-info-50 border-info-200 dark:bg-info-400/10 dark:border-info-400/20',
        default => 'bg-gray-50 border-gray-200 dark:bg-white/5 dark:border-white/10',
    };

    $badgeClasses = match ($color) {
        'primary' => 'bg-primary-100 text-primary-700 dark:bg-primary-400/20 dark:text-primary-400',
        'success' => 'bg-success-100 text-success-700 dark:bg-success-400/20 dark:text-success-400',
        'warning' => 'bg-warning-100 text-warning-700 dark:bg-warning-400/20 dark:text-warning-400',
        'info' => 'bg-info-100 text-info-700 dark:bg-info-400/20 dark:text-info-400',
        default => 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-400',
    };
@endphp

<section
    class="fi-kanban-column flex-shrink-0 rounded-xl border {{ $colorClasses }}"
    style="width: {{ $board->getColumnWidth() }};"
    role="group"
    aria-label="{{ $label }}"
>
    <header class="fi-kanban-column-header flex items-center justify-between gap-3 p-3">
        <h3 class="fi-kanban-column-title truncate text-sm font-semibold text-gray-950 dark:text-white">
            {{ $label }}
        </h3>

        <span class="fi-kanban-column-count inline-flex items-center justify-center rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeClasses }}">
            {{ $column->count }}
        </span>
    </header>

    <div
        class="fi-kanban-column-records space-y-3 p-2"
        style="min-height: 72px;"
        role="list"
    >
        @forelse ($column->records as $record)
            @include(
                $board->getCardView(),
                [
                    'record' => $record,
                    'board' => $board,
                    'column' => $column,
                ]
            )
        @empty
            <div class="fi-kanban-column-empty flex flex-col items-center justify-center px-3 py-8 text-center">
                <x-filament::icon
                    icon="heroicon-o-calendar-days"
                    class="mb-2 h-8 w-8 text-gray-300 dark:text-gray-600"
                />

                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                    Nenhuma visita
                </p>

                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                    Não existem visitas nesta situação.
                </p>
            </div>
        @endforelse
    </div>
</section>
