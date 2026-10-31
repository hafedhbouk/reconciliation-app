{{--
    Mise en page des pages publiques (connexion, mot de passe oublié), via <x-guest-layout>.
    Grand écran : panneau de présentation à gauche, formulaire à droite. Mobile : logo puis formulaire.
    La syntaxe Blade est expliquée dans layouts/app.blade.php.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @vite(['resources/css/app.scss', 'resources/js/app.js'])

        <script>
            (function () {
                const theme = localStorage.getItem('theme') ||
                    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-bs-theme', theme);
            })();
        </script>
    </head>
    <body>
        <div class="auth-shell">
            <section class="auth-hero" aria-hidden="true">
                <x-application-logo class="auth-hero-logo" />
                <h1 class="mb-0">{{ __('Rapprochement des transactions') }}</h1>
                <p class="auth-hero-text mb-0">{{ config('app.name') }}</p>
                <ul class="auth-features">
                    <li><i class="bi bi-cloud-arrow-up"></i>{{ __('Import des fichiers ALPHA, BNA, SMT et WEB') }}</li>
                    <li><i class="bi bi-signpost-split"></i>{{ __('Rapprochement automatique et manuel') }}</li>
                    <li><i class="bi bi-graph-up-arrow"></i>{{ __('Tableau de bord et suivi des écarts') }}</li>
                </ul>
            </section>

            <main class="auth-panel">
                <a href="/" class="d-lg-none">
                    <x-application-logo class="auth-logo" />
                </a>

                <div class="card auth-card">
                    <div class="card-body">
                        {{ $slot }}
                    </div>
                </div>

                <footer class="auth-footer">
                    <span>© 2026 HANDOURA Houcine — Tous droits réservés</span>
                    <span class="d-block fw-semibold mt-1">Version 1.0</span>
                </footer>
            </main>
        </div>
    </body>
</html>
