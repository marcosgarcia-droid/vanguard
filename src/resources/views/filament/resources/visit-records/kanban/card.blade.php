@php
    use App\Modules\Operations\Domain\Visits\VisitStatus;
    use App\Support\VanguardText;

    $status = $record->status instanceof VisitStatus
        ? $record->status
        : VisitStatus::tryFrom((string) $record->status);

    $statusColor = match ($status) {
        VisitStatus::Scheduled => 'info',
        VisitStatus::PendingAuthorization => 'warning',
        VisitStatus::Authorized => 'success',
        VisitStatus::InProgress => 'primary',
        VisitStatus::Completed => 'success',
        default => 'gray',
    };

    $statusClasses = match ($statusColor) {
        'primary' => 'bg-primary-50 text-primary-700 dark:bg-primary-400/10 dark:text-primary-400',
        'success' => 'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400',
        'warning' => 'bg-warning-50 text-warning-700 dark:bg-warning-400/10 dark:text-warning-400',
        'info' => 'bg-info-50 text-info-700 dark:bg-info-400/10 dark:text-info-400',
        default => 'bg-gray-50 text-gray-700 dark:bg-gray-400/10 dark:text-gray-400',
    };

    $photoUrl = $this->visitorPhotoUrl($record);

    $visitorName = VanguardText::upper(
        $record->visitor?->display_name
            ?: $record->visitor?->full_name
            ?: 'VISITANTE'
    );

    $officialName = VanguardText::upper(
        $record->visitor?->full_name
    );

    $organizationName = VanguardText::upper(
        $record->organization?->operational_name
            ?: $record->organization?->display_name
            ?: $record->organization?->legal_name
    );

    $visitedName = VanguardText::upper(
        $record->hostEmployee?->full_name
    );

    $partnerName = VanguardText::upper(
        $record->partner?->display_name
            ?: $record->partner?->trade_name
            ?: $record->partner?->legal_name
    );

    $purpose = VanguardText::upper(
        $record->purpose
    );

    $expectedStart = $record->expected_start_at?->format(
        'd/m/Y H:i'
    );

    $expectedEnd = $record->expected_end_at?->format(
        'd/m/Y H:i'
    );
@endphp

<article
    data-kanban-card
    data-record-id="{{ $record->getKey() }}"
    role="listitem"
    aria-label="{{ $visitorName }}"
    class="fi-kanban-card overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 transition-shadow hover:shadow-md dark:bg-gray-900 dark:ring-white/10"
>
    <div class="fi-kanban-card-header flex gap-3 p-3">
        <div class="fi-kanban-card-photo-wrap shrink-0">
            @if ($photoUrl)
                <img
                    src="{{ $photoUrl }}"
                    alt="Foto de {{ $visitorName }}"
                    class="fi-kanban-card-photo h-24 w-20 rounded-lg object-cover ring-1 ring-gray-950/10 dark:ring-white/10"
                    loading="lazy"
                />
            @else
                <div
                    class="fi-kanban-card-initials flex h-24 w-20 items-center justify-center rounded-lg bg-gray-100 text-xl font-semibold text-gray-500 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10"
                    aria-label="Visitante sem foto"
                >
                    {{ $this->visitorInitials($record) }}
                </div>
            @endif
        </div>

        <div class="fi-kanban-card-summary min-w-0 flex-1">
            <div class="fi-kanban-card-title-row flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <h4 class="fi-kanban-card-name text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $visitorName }}
                    </h4>

                    @if (
                        filled($officialName)
                        && $officialName !== $visitorName
                    )
                        <p class="fi-kanban-card-official-name mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ $officialName }}
                        </p>
                    @endif
                </div>

                {{ ($this->viewVisitAction)(['record' => $record->getKey()]) }}
            </div>

            <div class="fi-kanban-card-badges mt-2 flex flex-wrap gap-1">
                <span class="fi-kanban-badge inline-flex items-center rounded-md px-1.5 py-0.5 text-[11px] font-medium {{ $statusClasses }}">
                    {{ VanguardText::upper($status?->label()) }}
                </span>

                @if ($record->arrived_at)
                    <span class="fi-kanban-badge inline-flex items-center rounded-md bg-warning-50 px-1.5 py-0.5 text-[11px] font-medium text-warning-700 dark:bg-warning-400/10 dark:text-warning-400">
                        CHEGADA REGISTRADA
                    </span>
                @endif

                @if ($record->checked_in_at && ! $record->checked_out_at)
                    <span class="fi-kanban-badge inline-flex items-center rounded-md bg-primary-50 px-1.5 py-0.5 text-[11px] font-medium text-primary-700 dark:bg-primary-400/10 dark:text-primary-400">
                        DENTRO DA UNIDADE
                    </span>
                @endif

                @if ($record->checked_out_at)
                    <span class="fi-kanban-badge inline-flex items-center rounded-md bg-success-50 px-1.5 py-0.5 text-[11px] font-medium text-success-700 dark:bg-success-400/10 dark:text-success-400">
                        SAÍDA REGISTRADA
                    </span>
                @endif
            </div>
        </div>
    </div>

    <dl class="fi-kanban-card-details space-y-2 border-t border-gray-100 px-3 py-3 text-xs dark:border-white/5">
        <div class="fi-kanban-card-detail flex items-start gap-2">
            <dt class="fi-kanban-card-label w-20 shrink-0 font-medium text-gray-500 dark:text-gray-400">
                Previsão
            </dt>
            <dd class="fi-kanban-card-value text-gray-800 dark:text-gray-200">
                {{ $expectedStart ?: '-' }}
                @if ($expectedEnd)
                    <span class="text-gray-400">
                        até {{ $expectedEnd }}
                    </span>
                @endif
            </dd>
        </div>

        <div class="fi-kanban-card-detail flex items-start gap-2">
            <dt class="fi-kanban-card-label w-20 shrink-0 font-medium text-gray-500 dark:text-gray-400">
                Unidade
            </dt>
            <dd class="fi-kanban-card-value min-w-0 break-words text-gray-800 dark:text-gray-200">
                {{ $organizationName ?: '-' }}
            </dd>
        </div>

        <div class="fi-kanban-card-detail flex items-start gap-2">
            <dt class="fi-kanban-card-label w-20 shrink-0 font-medium text-gray-500 dark:text-gray-400">
                Visitado
            </dt>
            <dd class="fi-kanban-card-value min-w-0 break-words text-gray-800 dark:text-gray-200">
                {{ $visitedName ?: '-' }}
            </dd>
        </div>

        <div class="fi-kanban-card-detail flex items-start gap-2">
            <dt class="fi-kanban-card-label w-20 shrink-0 font-medium text-gray-500 dark:text-gray-400">
                Empresa
            </dt>
            <dd class="fi-kanban-card-value min-w-0 break-words text-gray-800 dark:text-gray-200">
                {{ $partnerName ?: '-' }}
            </dd>
        </div>

        <div class="fi-kanban-card-detail flex items-start gap-2">
            <dt class="fi-kanban-card-label w-20 shrink-0 font-medium text-gray-500 dark:text-gray-400">
                Finalidade
            </dt>
            <dd class="fi-kanban-card-value min-w-0 break-words text-gray-800 dark:text-gray-200">
                {{ $purpose ?: '-' }}
            </dd>
        </div>
    </dl>

    <footer class="fi-kanban-card-actions flex flex-wrap items-center justify-end gap-1 border-t border-gray-100 px-3 py-2 dark:border-white/5">
        {{ ($this->registerVisitArrivalAction)(['record' => $record->getKey()]) }}
        {{ ($this->authorizeVisitAction)(['record' => $record->getKey()]) }}
        {{ ($this->rejectVisitAction)(['record' => $record->getKey()]) }}
        {{ ($this->checkInVisitAction)(['record' => $record->getKey()]) }}
        {{ ($this->checkOutVisitAction)(['record' => $record->getKey()]) }}
        {{ ($this->cancelVisitAction)(['record' => $record->getKey()]) }}
    </footer>
</article>
