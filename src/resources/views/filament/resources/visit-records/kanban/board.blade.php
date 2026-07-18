@php
    $board = $this->getKanbanBoard();
    $columns = $this->getKanbanColumns();
    $hasFilters = $board->hasFilters();
    $hasSearch = $board->isSearchable();
    $hasLoading = $board->hasLoading();
    $activeFilterCount = collect($this->kanbanFilters)
        ->filter(fn ($value) => filled($value))
        ->count();
@endphp

<div class="fi-kanban-board">
    @if ($hasSearch || $hasFilters)
        <div class="fi-kanban-toolbar mb-4 flex flex-wrap items-center gap-2">
            @if ($hasSearch)
                <div class="fi-kanban-search w-full sm:w-80">
                    <div class="fi-kanban-search-inner relative">
                        <div class="fi-kanban-search-icon pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                            <x-filament::icon
                                icon="heroicon-m-magnifying-glass"
                                class="h-5 w-5 text-gray-400 dark:text-gray-500"
                            />
                        </div>

                        <input
                            type="search"
                            wire:model.live.debounce.300ms="kanbanSearch"
                            placeholder="Buscar visitante, finalidade, unidade ou visitado..."
                            aria-label="Buscar visitas no Kanban"
                            class="fi-kanban-search-input fi-input block w-full rounded-lg border-none bg-white py-2 ps-10 pe-3 text-sm shadow-sm ring-1 ring-gray-950/10 transition duration-75 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:placeholder:text-gray-500"
                        />
                    </div>
                </div>
            @endif

            @if ($hasFilters)
                <div
                    x-data="{ open: false }"
                    class="fi-kanban-filter relative"
                >
                    <button
                        type="button"
                        x-on:click="open = ! open"
                        :aria-expanded="open.toString()"
                        aria-controls="visit-kanban-filters"
                        aria-label="Abrir filtros"
                        @class([
                            'fi-kanban-filter-button relative flex items-center justify-center rounded-lg p-2 text-sm shadow-sm ring-1 transition duration-75',
                            'bg-white text-gray-700 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-300 dark:ring-white/20 dark:hover:bg-white/10' => $activeFilterCount === 0,
                            'bg-primary-50 text-primary-700 ring-primary-200 hover:bg-primary-100 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30' => $activeFilterCount > 0,
                        ])
                    >
                        <x-filament::icon
                            icon="heroicon-m-funnel"
                            class="h-5 w-5"
                        />

                        @if ($activeFilterCount > 0)
                            <span class="absolute -end-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-primary-500 text-[10px] font-bold text-white">
                                {{ $activeFilterCount }}
                            </span>
                        @endif
                    </button>

                    <div
                        x-show="open"
                        x-on:click.outside="open = false"
                        x-transition
                        id="visit-kanban-filters"
                        role="dialog"
                        aria-label="Filtros das visitas"
                        class="fi-kanban-filter-popover absolute end-0 z-20 mt-2 w-80 rounded-xl bg-white p-4 shadow-lg ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                        x-cloak
                    >
                        <form
                            wire:submit="applyKanbanFilters"
                            x-on:submit="open = false"
                        >
                            <div class="fi-kanban-filter-heading">
                                <h4>Filtros</h4>
                            </div>

                            {{ $this->kanbanFiltersForm }}

                            <div class="fi-kanban-filter-actions">
                                <button
                                    type="button"
                                    wire:click="clearKanbanFilters"
                                    x-on:click="open = false"
                                    class="fi-kanban-filter-clear"
                                >
                                    Limpar filtros
                                </button>

                                <button
                                    type="submit"
                                    class="fi-kanban-filter-apply"
                                >
                                    Aplicar
                                </button>
                            </div>
                        </form>
</div>
                </div>
            @endif
        </div>
    @endif

    <div class="fi-kanban-notice mb-4 flex items-start gap-2 rounded-xl bg-info-50 p-3 text-sm text-info-700 ring-1 ring-info-200 dark:bg-info-400/10 dark:text-info-300 dark:ring-info-400/20">
        <x-filament::icon
            icon="heroicon-m-information-circle"
            class="mt-0.5 h-5 w-5 shrink-0"
        />

        <span>
            A situação das visitas é alterada somente pelas ações de cada card.
            A movimentação por arrastar está desabilitada.
        </span>
    </div>

    @if ($hasLoading)
        <div
            wire:loading.delay
            class="fi-kanban-loading mb-3 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
        >
            <x-filament::loading-indicator class="h-5 w-5" />
            Atualizando o Kanban...
        </div>
    @endif

    <div
        class="fi-kanban-columns flex items-start gap-4 overflow-x-auto pb-4"
        style="min-height: 60vh;"
    >
        @foreach ($columns as $column)
            @include(
                $board->getColumnView(),
                [
                    'column' => $column,
                    'board' => $board,
                ]
            )
        @endforeach
    </div>

    <x-filament-actions::modals />
</div>
