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

        @can('search.viewAny')
            <li class="nav-item">
                <a href="{{ route('admin.search.index') }}" class="nav-link {{ request()->routeIs('admin.search.*') ? 'active' : '' }}">
                    <i class="bi bi-search me-2"></i>{{ __('Recherche') }}
                </a>
            </li>
        @endcan

        @can('imports.viewAny')
            <li class="nav-item mt-3">
                <span class="text-uppercase text-secondary small fw-semibold px-2">{{ __('Imports') }}</span>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.imports.index') }}" class="nav-link {{ request()->routeIs('admin.imports.*') ? 'active' : '' }}">
                    <i class="bi bi-upload me-2"></i>{{ __('Imports') }}
                </a>
            </li>
        @endcan

        @canany(['matching-rules.viewAny', 'matching-results.viewAny', 'exceptions.viewAny'])
            <li class="nav-item mt-3">
                <span class="text-uppercase text-secondary small fw-semibold px-2">{{ __('Rapprochement') }}</span>
            </li>
            @can('matching-rules.viewAny')
                <li class="nav-item">
                    <a href="{{ route('admin.matching-rules.index') }}" class="nav-link {{ request()->routeIs('admin.matching-rules.*') ? 'active' : '' }}">
                        <i class="bi bi-signpost-split me-2"></i>{{ __('Règles de rapprochement') }}
                    </a>
                </li>
            @endcan
            @can('matching-results.viewAny')
                <li class="nav-item">
                    <a href="{{ route('admin.matching-results.index') }}" class="nav-link {{ request()->routeIs('admin.matching-results.*') ? 'active' : '' }}">
                        <i class="bi bi-link-45deg me-2"></i>{{ __('Résultats de rapprochement') }}
                    </a>
                </li>
            @endcan
            @can('matching-results.create')
                <li class="nav-item">
                    <a href="{{ route('admin.reconciliation.index') }}" class="nav-link {{ request()->routeIs('admin.reconciliation.*') ? 'active' : '' }}">
                        <i class="bi bi-hand-index-thumb me-2"></i>{{ __('Rapprochement manuel') }}
                    </a>
                </li>
            @endcan
            @can('exceptions.viewAny')
                <li class="nav-item">
                    <a href="{{ route('admin.exceptions.index') }}" class="nav-link {{ request()->routeIs('admin.exceptions.*') ? 'active' : '' }}">
                        <i class="bi bi-exclamation-triangle me-2"></i>{{ __('Exceptions') }}
                    </a>
                </li>
            @endcan
        @endcanany

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
