{{--
    Composant <x-admin.sidebar> : le menu latéral. Chaque lien n'est affiché qu'avec la permission correspondante (@can).
    Une section s'ouvre d'elle-même quand la page affichée en fait partie ($open...).
    Sur mobile, le menu devient un tiroir (ouverture/fermeture : layouts/app.blade.php).
--}}
@php
    $openRapprochement = request()->routeIs('admin.matching-rules.*', 'admin.matching-results.*', 'admin.reconciliation.*', 'admin.exceptions.*');
    $openParametrage = request()->routeIs('admin.banks.*', 'admin.sources.*', 'admin.settings.*', 'admin.currencies.*');
    $openAdministration = request()->routeIs('admin.users.*', 'admin.roles.*');
@endphp

<nav class="admin-sidebar" aria-label="{{ __('Menu principal') }}">
    <a href="{{ route('dashboard') }}" class="sidebar-brand">
        <x-application-logo class="sidebar-logo" />
        <span class="sidebar-app-name">{{ config('app.name') }}</span>
    </a>
    <button type="button" class="btn-close sidebar-close d-lg-none m-2" data-sidebar-close aria-label="{{ __('Fermer le menu') }}"></button>

    <div class="sidebar-label">{{ __('Navigation') }}</div>
    <ul class="sidebar-nav">
        <li>
            <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-grid-1x2"></i>{{ __('Tableau de bord') }}
            </a>
        </li>
        <li>
            <a href="{{ route('admin.operations.index') }}" class="sidebar-link {{ request()->routeIs('admin.operations.*') ? 'active' : '' }}">
                <i class="bi bi-activity"></i>{{ __('Mes opérations') }}
            </a>
        </li>
        @can('search.viewAny')
            <li>
                <a href="{{ route('admin.search.index') }}" class="sidebar-link {{ request()->routeIs('admin.search.*') ? 'active' : '' }}">
                    <i class="bi bi-search"></i>{{ __('Recherche') }}
                </a>
            </li>
        @endcan
        @can('imports.viewAny')
            <li>
                <a href="{{ route('admin.imports.index') }}" class="sidebar-link {{ request()->routeIs('admin.imports.*') ? 'active' : '' }}">
                    <i class="bi bi-cloud-arrow-up"></i>{{ __('Imports') }}
                </a>
            </li>
        @endcan
    </ul>

    @canany(['matching-rules.viewAny', 'matching-results.viewAny', 'exceptions.viewAny', 'banks.viewAny', 'sources.viewAny', 'settings.viewAny', 'users.viewAny', 'roles.viewAny', 'audit-logs.viewAny'])
        <div class="sidebar-label">{{ __('Gestion') }}</div>
    @endcanany
    <ul class="sidebar-nav mb-auto">
        @canany(['matching-rules.viewAny', 'matching-results.viewAny', 'exceptions.viewAny'])
            <li>
                <button class="sidebar-link" type="button" data-bs-toggle="collapse" data-bs-target="#section-rapprochement"
                        aria-expanded="{{ $openRapprochement ? 'true' : 'false' }}" aria-controls="section-rapprochement">
                    <i class="bi bi-signpost-split"></i>{{ __('Rapprochement') }}<i class="bi bi-chevron-down sidebar-caret"></i>
                </button>
                <div class="collapse {{ $openRapprochement ? 'show' : '' }}" id="section-rapprochement">
                    <ul class="sidebar-subnav">
                        @can('matching-rules.viewAny')
                            <li><a href="{{ route('admin.matching-rules.index') }}" class="sidebar-link {{ request()->routeIs('admin.matching-rules.*') ? 'active' : '' }}">{{ __('Règles de rapprochement') }}</a></li>
                        @endcan
                        @can('matching-results.viewAny')
                            <li><a href="{{ route('admin.matching-results.index') }}" class="sidebar-link {{ request()->routeIs('admin.matching-results.*') ? 'active' : '' }}">{{ __('Résultats de rapprochement') }}</a></li>
                        @endcan
                        @can('matching-results.create')
                            <li><a href="{{ route('admin.reconciliation.index') }}" class="sidebar-link {{ request()->routeIs('admin.reconciliation.index') ? 'active' : '' }}">{{ __('Rapprochement manuel') }}</a></li>
                        @endcan
                        @can('matching-results.viewAny')
                            <li><a href="{{ route('admin.reconciliation.unmatched') }}" class="sidebar-link {{ request()->routeIs('admin.reconciliation.unmatched') ? 'active' : '' }}">{{ __('Écarts par source') }}</a></li>
                        @endcan
                        @can('exceptions.viewAny')
                            <li><a href="{{ route('admin.exceptions.index') }}" class="sidebar-link {{ request()->routeIs('admin.exceptions.*') ? 'active' : '' }}">{{ __('Exceptions') }}</a></li>
                        @endcan
                    </ul>
                </div>
            </li>
        @endcanany

        @canany(['banks.viewAny', 'sources.viewAny', 'settings.viewAny'])
            <li>
                <button class="sidebar-link" type="button" data-bs-toggle="collapse" data-bs-target="#section-parametrage"
                        aria-expanded="{{ $openParametrage ? 'true' : 'false' }}" aria-controls="section-parametrage">
                    <i class="bi bi-sliders"></i>{{ __('Paramétrage') }}<i class="bi bi-chevron-down sidebar-caret"></i>
                </button>
                <div class="collapse {{ $openParametrage ? 'show' : '' }}" id="section-parametrage">
                    <ul class="sidebar-subnav">
                        @can('banks.viewAny')
                            <li><a href="{{ route('admin.banks.index') }}" class="sidebar-link {{ request()->routeIs('admin.banks.*') ? 'active' : '' }}">{{ __('Banques') }}</a></li>
                        @endcan
                        @can('sources.viewAny')
                            <li><a href="{{ route('admin.sources.index') }}" class="sidebar-link {{ request()->routeIs('admin.sources.*') ? 'active' : '' }}">{{ __('Sources') }}</a></li>
                        @endcan
                        @can('settings.viewAny')
                            <li><a href="{{ route('admin.settings.index') }}" class="sidebar-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">{{ __('Paramètres') }}</a></li>
                        @endcan
                    </ul>
                </div>
            </li>
        @endcanany

        @canany(['users.viewAny', 'roles.viewAny'])
            <li>
                <button class="sidebar-link" type="button" data-bs-toggle="collapse" data-bs-target="#section-administration"
                        aria-expanded="{{ $openAdministration ? 'true' : 'false' }}" aria-controls="section-administration">
                    <i class="bi bi-people"></i>{{ __('Administration') }}<i class="bi bi-chevron-down sidebar-caret"></i>
                </button>
                <div class="collapse {{ $openAdministration ? 'show' : '' }}" id="section-administration">
                    <ul class="sidebar-subnav">
                        @can('users.viewAny')
                            <li><a href="{{ route('admin.users.index') }}" class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">{{ __('Utilisateurs') }}</a></li>
                        @endcan
                        @can('roles.viewAny')
                            <li><a href="{{ route('admin.roles.index') }}" class="sidebar-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">{{ __('Rôles & Permissions') }}</a></li>
                        @endcan
                    </ul>
                </div>
            </li>
        @endcanany

        @can('audit-logs.viewAny')
            <li>
                <a href="{{ route('admin.audit-logs.index') }}" class="sidebar-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">
                    <i class="bi bi-journal-text"></i>{{ __('Journal d\'audit') }}
                </a>
            </li>
        @endcan
    </ul>
</nav>
