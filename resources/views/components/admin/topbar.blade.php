{{--
    Composant <x-admin.topbar> : barre du haut (bouton menu mobile, opérations, notifications, thème clair/sombre, menu utilisateur).
--}}
@php
    use App\Notifications\ImportProcessedNotification;
    use App\Notifications\MatchingActionCompletedNotification;

    $unreadNotifications = auth()->user()->unreadNotifications()->latest()->take(8)->get();
    $unreadCount = auth()->user()->unreadNotifications()->count();
    $activeOperations = \App\Models\Operation::query()->where('user_id', auth()->id())
        ->whereIn('status', ['pending', 'running'])->count();
    // Initiales de l'avatar : premières lettres des deux premiers mots du nom.
    $initials = collect(preg_split('/\s+/', trim(Auth::user()->name)))->filter()->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
@endphp

<header class="app-topbar">
    <div class="d-flex align-items-center gap-2 min-w-0">
        <button type="button" id="mobileMenuToggle" class="btn-icon mobile-menu-toggle" aria-label="{{ __('Ouvrir le menu') }}" aria-expanded="false">
            <i class="bi bi-list"></i>
        </button>
        <a href="{{ route('dashboard') }}" class="d-lg-none" aria-label="{{ __('Tableau de bord') }}">
            <x-application-logo class="topbar-logo" />
        </a>
    </div>

    <div class="d-flex align-items-center gap-1 gap-sm-2">
        {{-- Opérations en cours : pastille mise à jour par resources/js/operations.js. --}}
        <a href="{{ route('admin.operations.index') }}" class="btn-icon"
           title="{{ __('Mes opérations') }}" aria-label="{{ __('Mes opérations') }}"
           data-operations-indicator data-url="{{ route('admin.operations.data') }}" data-active-count="{{ $activeOperations }}">
            <i class="bi bi-activity"></i>
            <span class="badge badge-solid rounded-pill bg-primary {{ $activeOperations === 0 ? 'd-none' : '' }}"
                  data-operations-count>{{ $activeOperations }}</span>
        </a>

        <div class="dropdown">
            <button class="btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('Notifications') }}" aria-label="{{ __('Notifications') }}">
                <i class="bi bi-bell"></i>
                @if ($unreadCount > 0)
                    <span class="badge badge-solid rounded-pill bg-danger">{{ $unreadCount }}</span>
                @endif
            </button>
            <div class="dropdown-menu dropdown-menu-end p-0 notification-menu">
                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                    <span class="fw-semibold">{{ __('Notifications') }}</span>
                    @if ($unreadCount > 0)
                        <span class="badge rounded-pill bg-primary">{{ $unreadCount }}</span>
                    @endif
                </div>
                <div class="list-group list-group-flush notification-list">
                    @forelse ($unreadNotifications as $notification)
                        @php($isImport = $notification->type === ImportProcessedNotification::class)
                        <div class="list-group-item small d-flex gap-2 align-items-start">
                            <span class="icon-chip {{ $isImport ? 'tone-cyan' : 'tone-violet' }}">
                                <i class="bi {{ $isImport ? 'bi-cloud-arrow-up' : 'bi-signpost-split' }}"></i>
                            </span>
                            <div class="min-w-0">
                                @if ($isImport)
                                    <div class="fw-semibold">{{ __('Import :source terminé', ['source' => $notification->data['source_code'] ?? '?']) }}</div>
                                @elseif ($notification->type === MatchingActionCompletedNotification::class)
                                    <div class="fw-semibold">{{ $notification->data['title'] ?? '' }}</div>
                                @endif
                                <div class="text-secondary">{{ \App\Support\DateDisplay::relative($notification->created_at) }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item small text-secondary text-center py-4">
                            <i class="bi bi-bell-slash d-block fs-4 mb-1"></i>{{ __('Aucune nouvelle notification.') }}
                        </div>
                    @endforelse
                </div>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-link p-0">{{ __('Tout marquer comme lu') }}</button>
                    </form>
                    <a href="{{ route('notifications.index') }}" class="small fw-semibold">{{ __('Voir tout') }}</a>
                </div>
            </div>
        </div>

        <button type="button" id="theme-toggle" class="btn-icon" title="{{ __('Basculer le thème') }}" aria-label="{{ __('Basculer le thème') }}">
            <i class="bi bi-moon-stars d-none" id="theme-icon-dark"></i>
            <i class="bi bi-sun d-none" id="theme-icon-light"></i>
        </button>

        <div class="dropdown">
            <button class="user-menu-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Menu utilisateur') }}">
                <span class="avatar" aria-hidden="true">{{ $initials }}</span>
                <span class="user-name d-none d-sm-inline">{{ Auth::user()->name }}</span>
                <i class="bi bi-chevron-down small text-secondary d-none d-sm-inline"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li class="px-3 py-2">
                    <div class="fw-semibold">{{ Auth::user()->name }}</div>
                    <div class="small text-secondary text-truncate">{{ Auth::user()->email }}</div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>{{ __('Profil') }}</a></li>
                <li><a class="dropdown-item" href="{{ route('notifications.index') }}"><i class="bi bi-bell me-2"></i>{{ __('Notifications') }}</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>{{ __('Déconnexion') }}</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>

<script>
    (function () {
        const toggle = document.getElementById('theme-toggle');
        const iconDark = document.getElementById('theme-icon-dark');
        const iconLight = document.getElementById('theme-icon-light');

        function syncIcon() {
            const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
            iconDark.classList.toggle('d-none', isDark);
            iconLight.classList.toggle('d-none', !isDark);
        }

        toggle.addEventListener('click', function () {
            const current = document.documentElement.getAttribute('data-bs-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', next);
            localStorage.setItem('theme', next);
            syncIcon();
        });

        syncIcon();
    })();
</script>
