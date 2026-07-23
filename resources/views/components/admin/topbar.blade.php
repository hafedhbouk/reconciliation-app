<nav class="navbar navbar-expand bg-body border-bottom px-3">
    <div class="container-fluid px-0 justify-content-end">
        <div class="d-flex align-items-center gap-2">
            @php
                $unreadNotifications = auth()->user()->unreadNotifications()->latest()->take(8)->get();
                $unreadCount = auth()->user()->unreadNotifications()->count();
            @endphp
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm position-relative dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('Notifications') }}">
                    <i class="bi bi-bell"></i>
                    @if ($unreadCount > 0)
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $unreadCount }}</span>
                    @endif
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0" style="width: 320px;">
                    <div class="list-group list-group-flush" style="max-height: 320px; overflow-y: auto;">
                        @forelse ($unreadNotifications as $notification)
                            <div class="list-group-item small">
                                @if ($notification->type === \App\Notifications\ImportProcessedNotification::class)
                                    <div class="fw-semibold">{{ __('Import :source terminé', ['source' => $notification->data['source_code'] ?? '?']) }}</div>
                                @elseif ($notification->type === \App\Notifications\MatchingActionCompletedNotification::class)
                                    <div class="fw-semibold">{{ $notification->data['title'] ?? '' }}</div>
                                @endif
                                <div class="text-secondary">{{ $notification->created_at->diffForHumans() }}</div>
                            </div>
                        @empty
                            <div class="list-group-item small text-secondary">{{ __('Aucune nouvelle notification.') }}</div>
                        @endforelse
                    </div>
                    <div class="d-flex justify-content-between p-2 border-top">
                        <form method="POST" action="{{ route('notifications.read-all') }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-link p-0">{{ __('Tout marquer comme lu') }}</button>
                        </form>
                        <a href="{{ route('notifications.index') }}" class="small">{{ __('Voir tout') }}</a>
                    </div>
                </div>
            </div>

            <button type="button" id="theme-toggle" class="btn btn-outline-secondary btn-sm" title="{{ __('Basculer le thème') }}">
                <i class="bi bi-moon-stars d-none" id="theme-icon-dark"></i>
                <i class="bi bi-sun-fill d-none" id="theme-icon-light"></i>
            </button>

            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle me-1"></i>{{ Auth::user()->name }}
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}">{{ __('Profil') }}</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">{{ __('Déconnexion') }}</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

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
