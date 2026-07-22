<nav class="admin-sidebar bg-body-tertiary border-end d-flex flex-column p-3" style="width: 260px; min-width: 260px;">
    <a href="{{ route('dashboard') }}" class="d-flex align-items-center mb-4 text-decoration-none text-body">
        <x-application-logo style="width: 2rem; height: 2rem; fill: currentColor;" class="me-2" />
        <span class="fs-5 fw-semibold">{{ config('app.name') }}</span>
    </a>

    <ul class="nav nav-pills flex-column mb-auto gap-1">
        <li class="nav-item">
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-speedometer2 me-2"></i>{{ __('Dashboard') }}
            </a>
        </li>

        @canany(['banks.viewAny', 'sources.viewAny', 'currencies.viewAny', 'holidays.viewAny', 'settings.viewAny'])
            <li class="nav-item mt-3">
                <span class="text-uppercase text-secondary small fw-semibold px-2">{{ __('Paramétrage') }}</span>
            </li>
            @can('banks.viewAny')
                <li class="nav-item">
                    <a href="{{ route('admin.banks.index') }}" class="nav-link {{ request()->routeIs('admin.banks.*') ? 'active' : '' }}">
                        <i class="bi bi-bank me-2"></i>{{ __('Banques') }}
                    </a>
                </li>
            @endcan
            @can('sources.viewAny')
                <li class="nav-item">
                    <a href="{{ route('admin.sources.index') }}" class="nav-link {{ request()->routeIs('admin.sources.*') ? 'active' : '' }}">
                        <i class="bi bi-diagram-3 me-2"></i>{{ __('Sources') }}
                    </a>
                </li>
            @endcan
            @can('currencies.viewAny')
                <li class="nav-item">
                    <a href="{{ route('admin.currencies.index') }}" class="nav-link {{ request()->routeIs('admin.currencies.*') ? 'active' : '' }}">
                        <i class="bi bi-cash-coin me-2"></i>{{ __('Devises') }}
                    </a>
                </li>
            @endcan
            @can('holidays.viewAny')
                <li class="nav-item">
                    <a href="{{ route('admin.holidays.index') }}" class="nav-link {{ request()->routeIs('admin.holidays.*') ? 'active' : '' }}">
                        <i class="bi bi-calendar-event me-2"></i>{{ __('Jours fériés') }}
                    </a>
                </li>
            @endcan
            @can('settings.viewAny')
                <li class="nav-item">
                    <a href="{{ route('admin.settings.index') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                        <i class="bi bi-sliders me-2"></i>{{ __('Paramètres') }}
                    </a>
                </li>
            @endcan
        @endcanany

        @canany(['users.viewAny', 'roles.viewAny'])
            <li class="nav-item mt-3">
                <span class="text-uppercase text-secondary small fw-semibold px-2">{{ __('Administration') }}</span>
            </li>
            @can('users.viewAny')
                <li class="nav-item">
                    <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                        <i class="bi bi-people me-2"></i>{{ __('Utilisateurs') }}
                    </a>
                </li>
            @endcan
            @can('roles.viewAny')
                <li class="nav-item">
                    <a href="{{ route('admin.roles.index') }}" class="nav-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
                        <i class="bi bi-shield-lock me-2"></i>{{ __('Rôles & Permissions') }}
                    </a>
                </li>
            @endcan
        @endcanany

        @can('audit-logs.viewAny')
            <li class="nav-item mt-3">
                <span class="text-uppercase text-secondary small fw-semibold px-2">{{ __('Suivi') }}</span>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.audit-logs.index') }}" class="nav-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">
                    <i class="bi bi-journal-text me-2"></i>{{ __('Journal d\'audit') }}
                </a>
            </li>
        @endcan
    </ul>
</nav>
