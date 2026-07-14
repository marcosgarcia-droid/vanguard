<div class="activity-log-timeline" x-data>
    @php
        use AlizHarb\ActivityLog\Support\ActivityChanges;
        use App\Support\ActivityLog\VanguardActivityLogPresenter;

        $activities = $activities ?? $getState() ?? collect();

        if (! $activities instanceof \Illuminate\Support\Collection) {
            $activities = collect($activities);
        }
    @endphp

    @forelse ($activities as $key => $activity)
        @php
            $oldValues = ActivityChanges::getOldValues($activity);
            $newValues = ActivityChanges::getNewValues($activity);
            $hasChanges = ActivityChanges::hasChanges($activity);
            $operationDetails =
                VanguardActivityLogPresenter::operationDetails(
                    $activity
                );
        @endphp

        <div class="activity-log-item group {{ ($slim ?? false) ? 'activity-log-item-slim' : '' }}">
            @if (! $loop->last)
                <div class="activity-log-line"></div>
            @endif

            <div class="activity-log-icon-wrapper {{ ($slim ?? false) ? 'activity-log-icon-wrapper-slim' : '' }}">
                @php
                    $config = config('filament-activity-log.events.' . $activity->event, [
                        'icon' => 'heroicon-m-information-circle',
                        'color' => 'gray',
                    ]);

                    $icon = $config['icon'];

                    $color = match ($config['color']) {
                        'success' => 'activity-log-text-success',
                        'warning' => 'activity-log-text-warning',
                        'danger' => 'activity-log-text-danger',
                        'info' => 'activity-log-text-info',
                        default => 'activity-log-text-gray',
                    };
                @endphp

                @if (! ($slim ?? false) && $activity->causer && method_exists($activity->causer, 'getFilamentAvatarUrl'))
                    <img src="{{ $activity->causer->getFilamentAvatarUrl() }}" alt="{{ $activity->causer->name }}" class="activity-log-avatar" />

                    <div class="activity-log-avatar-icon-wrapper">
                        <x-filament::icon :icon="$icon" class="activity-log-icon-xs {{ $color }}" />
                    </div>
                @else
                    <x-filament::icon :icon="$icon" class="{{ ($slim ?? false) ? 'activity-log-icon-md' : 'activity-log-icon-lg' }} {{ $color }}" />
                @endif
            </div>

            <div class="activity-log-card {{ ($slim ?? false) ? 'activity-log-card-slim' : '' }}">
                <div class="activity-log-header {{ ($slim ?? false) ? 'activity-log-header-slim' : '' }}">
                    <div class="activity-log-header-content">
                        <span class="activity-log-user">
                            {{ $activity->causer?->name ?? 'Sistema' }}
                        </span>

                        <span class="activity-log-event">
                            {{ VanguardActivityLogPresenter::eventLabel($activity->event) }}
                        </span>

                        @if (! ($slim ?? false))
                            <span class="activity-log-meta">
                                {{ VanguardActivityLogPresenter::subjectLabel($activity) }}
                            </span>
                        @endif
                    </div>

                    <div class="activity-log-meta-wrapper">
                        <time datetime="{{ $activity->created_at->toIso8601String() }}" class="activity-log-time" title="{{ $activity->created_at->format(config('filament-activity-log.datetime_format', 'd/m/Y H:i:s')) }}">
                            @if (! ($slim ?? false))
                                <x-filament::icon icon="heroicon-m-calendar" class="activity-log-icon-sm activity-log-icon-opacity-70" />
                            @endif

                            {{ $activity->created_at->diffForHumans() }}
                        </time>
                    </div>
                </div>

                <div class="activity-log-body {{ ($slim ?? false) ? 'activity-log-body-slim' : '' }}">
                    @if ($activity->description && (! ($slim ?? false) || $activity->description !== $activity->event))
                        <div class="activity-log-description {{ ($slim ?? false) ? 'activity-log-description-slim' : '' }}">
                            {{ $activity->description }}
                        </div>
                    @endif

                    @if (! empty($operationDetails))
                        <div
                            class="activity-log-changes-grid"
                            style="grid-template-columns: minmax(0, 1fr); margin-top: 0.75rem;"
                        >
                            <div class="activity-log-change-card">
                                <div class="activity-log-change-header">
                                    Detalhes da operação
                                </div>

                                <div class="activity-log-change-body">
                                    @foreach ($operationDetails as $detail)
                                        <div class="activity-log-change-item">
                                            <dt class="activity-log-change-key">
                                                {{ $detail['label'] }}
                                            </dt>

                                            <dd class="activity-log-change-value">
                                                {{ $detail['value'] }}
                                            </dd>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    @if (! ($slim ?? false) && (isset($activity->properties['ip_address']) || isset($activity->properties['user_agent'])))
                        <div class="activity-log-footer">
                            @if (isset($activity->properties['ip_address']))
                                <div class="activity-log-badge">
                                    <x-filament::icon icon="heroicon-m-globe-alt" class="activity-log-icon-sm" />
                                    {{ $activity->properties['ip_address'] }}
                                </div>
                            @endif

                            @if (isset($activity->properties['user_agent']))
                                <div class="activity-log-badge activity-log-badge-truncate" title="{{ $activity->properties['user_agent'] }}">
                                    <x-filament::icon icon="heroicon-m-device-phone-mobile" class="activity-log-icon-sm" />
                                    {{ $activity->properties['user_agent'] }}
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($hasChanges)
                        <div x-data="{ open: false }">
                            <button @click="open = !open" type="button" class="activity-log-changes-btn {{ ($slim ?? false) ? 'activity-log-changes-btn-slim' : '' }}">
                                <span class="activity-log-changes-btn-content">
                                    <x-filament::icon icon="heroicon-m-arrows-right-left" class="activity-log-icon-md" />
                                    Alterações
                                </span>

                                <x-filament::icon icon="heroicon-m-chevron-down" class="activity-log-icon-md activity-log-toggle-icon" x-bind:class="{ 'activity-log-rotate-180': open }" />
                            </button>

                            <div x-show="open" x-collapse class="activity-log-changes-grid" style="display: none;">
                                @if (! empty($oldValues))
                                    <div class="activity-log-change-card old">
                                        <div class="activity-log-change-header">
                                            Antes
                                        </div>

                                        <div class="activity-log-change-body">
                                            @foreach ($oldValues as $key => $value)
                                                <div class="activity-log-change-item">
                                                    <dt class="activity-log-change-key">
                                                        {{ VanguardActivityLogPresenter::fieldLabel($key) }}
                                                    </dt>

                                                    <dd class="activity-log-change-value">
                                                        {{ VanguardActivityLogPresenter::value($value) }}
                                                    </dd>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if (! empty($newValues))
                                    <div class="activity-log-change-card new">
                                        <div class="activity-log-change-header">
                                            Depois
                                        </div>

                                        <div class="activity-log-change-body">
                                            @foreach ($newValues as $key => $value)
                                                <div class="activity-log-change-item">
                                                    <dt class="activity-log-change-key">
                                                        {{ VanguardActivityLogPresenter::fieldLabel($key) }}
                                                    </dt>

                                                    <dd class="activity-log-change-value">
                                                        {{ VanguardActivityLogPresenter::value($value) }}
                                                    </dd>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="activity-log-empty-state">
            <div class="activity-log-empty-icon">
                <x-filament::icon icon="heroicon-o-clipboard-document-list" class="activity-log-icon-lg" style="width: 1.5rem; height: 1.5rem;" />
            </div>

            <h3 class="activity-log-empty-title">
                {{ __('filament-activity-log::activity.action.timeline.empty_state_title') }}
            </h3>

            <p class="activity-log-empty-description">
                {{ __('filament-activity-log::activity.action.timeline.empty_state_description') }}
            </p>
        </div>
    @endforelse
</div>
