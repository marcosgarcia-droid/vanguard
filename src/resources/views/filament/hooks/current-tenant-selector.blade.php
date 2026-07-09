@php
    use App\Models\User;
    use App\Modules\Identity\Application\Tenancy\TenantContext;
    use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;

    $user = auth()->user();
    $context = app(TenantContext::class);

    $shouldRender = $user instanceof User;

    $isSuperAdmin = $shouldRender
        && $user->hasRole(config('filament-shield.super_admin.name', 'super_admin'));

    $tenants = $shouldRender
        ? $context->availableTenantsForUser($user)
        : collect();

    $currentTenant = $shouldRender
        ? $context->currentTenantForUser($user)
        : null;

    $selectedTenantId = $isSuperAdmin
        ? ($context->selectedTenantId() ?? '__global__')
        : $context->currentTenantIdForUser($user);

    $shouldRender = $shouldRender && ($isSuperAdmin || $tenants->isNotEmpty());
@endphp

@if ($shouldRender)
    <div class="mb-3 border-b border-gray-200 px-4 pb-4 dark:border-white/10">
        <form method="POST" action="{{ route('vanguard.current-tenant.change') }}">
            @csrf

            <label
                for="vanguard-current-tenant"
                class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
            >
                Grupo empresarial
            </label>

            <select
                id="vanguard-current-tenant"
                name="tenant_id"
                onchange="this.form.submit()"
                class="vanguard-current-tenant-select block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-950 shadow-sm outline-none transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:focus:border-primary-500"
            >
                @if ($isSuperAdmin)
                    <option value="__global__" @selected($selectedTenantId === '__global__')>
                        Visão global
                    </option>
                @endif

                @foreach ($tenants as $tenant)
                    <option value="{{ $tenant->id }}" @selected($selectedTenantId === $tenant->id)>
                        {{ $tenant->name }}
                    </option>
                @endforeach
            </select>

            <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
                Atual:
                @if ($currentTenant instanceof TenantRecord)
                    {{ $currentTenant->name }}
                @elseif ($isSuperAdmin)
                    Visão global
                @else
                    Não definido
                @endif
            </p>
        </form>
    </div>
@endif
