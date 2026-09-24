<nav class="admin-sidebar bg-body-tertiary border-end d-flex flex-column p-3" style="width: 260px; min-width: 260px;">
    <a href="{{ route('dashboard') }}" class="d-flex flex-column align-items-center mb-4 text-decoration-none text-body">
        <x-application-logo style="height: 10rem; width: auto; max-width: 100%;" class="mb-2" />
        <span class="fw-semibold" style="font-size: .9rem; line-height: 1.2;">{{ config('app.name') }}</span>
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
            <li class="nav-item">
                <button class="nav-link sidebar-section-toggle d-flex align-items-center justify-content-between w-100 text-start p-2" type="button" data-bs-toggle="collapse" data-bs-target="#section-imports" aria-expanded="false" aria-controls="section-imports">
                    <span><i class="bi bi-upload me-2"></i>{{ __('Imports') }}</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="collapse" id="section-imports">
                    <ul class="nav nav-pills flex-column mb-2 ms-3">
                        <li class="nav-item">
                            <a href="{{ route('admin.imports.index') }}" class="nav-link {{ request()->routeIs('admin.imports.*') ? 'active' : '' }} small">
                                <i class="bi bi-chevron-right me-2"></i>{{ __('Imports') }}
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
        @endcan

        @canany(['matching-rules.viewAny', 'matching-results.viewAny', 'exceptions.viewAny'])
            <li class="nav-item">
                <button class="nav-link sidebar-section-toggle d-flex align-items-center justify-content-between w-100 text-start p-2" type="button" data-bs-toggle="collapse" data-bs-target="#section-rapprochement" aria-expanded="false" aria-controls="section-rapprochement">
                    <span><i class="bi bi-signpost-split me-2"></i>{{ __('Rapprochement') }}</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="collapse" id="section-rapprochement">
                    <ul class="nav nav-pills flex-column mb-2 ms-3">
                        @can('matching-rules.viewAny')
                            <li class="nav-item">
                                <a href="{{ route('admin.matching-rules.index') }}" class="nav-link {{ request()->routeIs('admin.matching-rules.*') ? 'active' : '' }} small">
                                    <i class="bi bi-chevron-right me-2"></i>{{ __('Règles de rapprochement') }}
                                </a>
                            </li>
                        @endcan
                        @can('matching-results.viewAny')
                            <li class="nav-item">
                                <a href="{{ route('admin.matching-results.index') }}" class="nav-link {{ request()->routeIs('admin.matching-results.*') ? 'active' : '' }} small">
                                    <i class="bi bi-chevron-right me-2"></i>{{ __('Résultats de rapprochement') }}
                                </a>
                            </li>
                        @endcan
                        @can('matching-results.create')
                            <li class="nav-item">
                                <a href="{{ route('admin.reconciliation.index') }}" class="nav-link {{ request()->routeIs('admin.reconciliation.index') ? 'active' : '' }} small">
                                    <i class="bi bi-chevron-right me-2"></i>{{ __('Rapprochement manuel') }}
                                </a>
                            </li>
                        @endcan
                        @can('matching-results.viewAny')
                            <li class="nav-item">
                                <a href="{{ route('admin.reconciliation.unmatched') }}" class="nav-link {{ request()->routeIs('admin.reconciliation.unmatched') ? 'active' : '' }} small">
                                    <i class="bi bi-chevron-right me-2"></i>{{ __('Écarts par source') }}
                                </a>
                            </li>
                        @endcan
                        @can('exceptions.viewAny')
                            <li class="nav-item">
                                <a href="{{ route('admin.exceptions.index') }}" class="nav-link {{ request()->routeIs('admin.exceptions.*') ? 'active' : '' }} small">
                                    <i class="bi bi-chevron-right me-2"></i>{{ __('Exceptions') }}
                                </a>
                            </li>
                        @endcan
                    </ul>
                </div>
            </li>
        @endcanany

        @canany(['banks.viewAny', 'sources.viewAny', 'settings.viewAny'])
            <li class="nav-item">
                <button class="nav-link sidebar-section-toggle d-flex align-items-center justify-content-between w-100 text-start p-2" type="button" data-bs-toggle="collapse" data-bs-target="#section-parametrage" aria-expanded="false" aria-controls="section-parametrage">
                    <span><i class="bi bi-sliders me-2"></i>{{ __('Paramétrage') }}</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="collapse" id="section-parametrage">
                    <ul class="nav nav-pills flex-column mb-2 ms-3">
                        @can('banks.viewAny')
                            <li class="nav-item">
                                <a href="{{ route('admin.banks.index') }}" class="nav-link {{ request()->routeIs('admin.banks.*') ? 'active' : '' }} small">
                                    <i class="bi bi-chevron-right me-2"></i>{{ __('Banques') }}
                                </a>
                            </li>
                        @endcan
                        @can('sources.viewAny')
                            <li class="nav-item">
                                <a href="{{ route('admin.sources.index') }}" class="nav-link {{ request()->routeIs('admin.sources.*') ? 'active' : '' }} small">
                                    <i class="bi bi-chevron-right me-2"></i>{{ __('Sources') }}
                                </a>
                            </li>
                        @endcan
                        @can('settings.viewAny')
                            <li class="nav-item">
                                <a href="{{ route('admin.settings.index') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }} small">
                                    <i class="bi bi-chevron-right me-2"></i>{{ __('Paramètres') }}
                                </a>
                            </li>
                        @endcan
                    </ul>
                </div>
            </li>
        @endcanany

        @canany(['users.viewAny', 'roles.viewAny'])
            <li class="nav-item">
                <button class="nav-link sidebar-section-toggle d-flex align-items-center justify-content-between w-100 text-start p-2" type="button" data-bs-toggle="collapse" data-bs-target="#section-administration" aria-expanded="false" aria-controls="section-administration">
                    <span><i class="bi bi-people me-2"></i>{{ __('Administration') }}</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="collapse" id="section-administration">
                    <ul class="nav nav-pills flex-column mb-2 ms-3">
                        @can('users.viewAny')
                            <li class="nav-item">
                                <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }} small">
                                    <i class="bi bi-chevron-right me-2"></i>{{ __('Utilisateurs') }}
                                </a>
                            </li>
                        @endcan
                        @can('roles.viewAny')
                            <li class="nav-item">
                                <a href="{{ route('admin.roles.index') }}" class="nav-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }} small">
                                    <i class="bi bi-chevron-right me-2"></i>{{ __('Rôles & Permissions') }}
                                </a>
                            </li>
                        @endcan
                    </ul>
                </div>
            </li>
        @endcanany

        @can('audit-logs.viewAny')
            <li class="nav-item">
                <button class="nav-link sidebar-section-toggle d-flex align-items-center justify-content-between w-100 text-start p-2" type="button" data-bs-toggle="collapse" data-bs-target="#section-suivi" aria-expanded="false" aria-controls="section-suivi">
                    <span><i class="bi bi-journal-text me-2"></i>{{ __('Suivi') }}</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="collapse" id="section-suivi">
                    <ul class="nav nav-pills flex-column mb-2 ms-3">
                        <li class="nav-item">
                            <a href="{{ route('admin.audit-logs.index') }}" class="nav-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }} small">
                                <i class="bi bi-chevron-right me-2"></i>{{ __('Journal d\'audit') }}
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
        @endcan
    </ul>
</nav>

<script>
    // Auto-rotate chevron on collapse/expand
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.sidebar-section-toggle').forEach(function (btn) {
            const target = document.querySelector(btn.dataset.bsTarget);
            if (!target) return;
            target.addEventListener('show.bs.collapse', function () {
                btn.querySelector('.bi-chevron-down')?.classList.add('rotate-180');
            });
            target.addEventListener('hide.bs.collapse', function () {
                btn.querySelector('.bi-chevron-down')?.classList.remove('rotate-180');
            });
        });
    });
</script>
