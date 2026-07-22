<nav class="navbar navbar-expand bg-body border-bottom px-3">
    <div class="container-fluid px-0 justify-content-end">
        <div class="d-flex align-items-center gap-2">
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
