<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
    <body class="antialiased">
        <div class="d-flex flex-column min-vh-100">
            <div class="d-flex flex-grow-1">
                <x-admin.sidebar />

                <div class="flex-grow-1 admin-content d-flex flex-column w-100">
                    <x-admin.topbar />

                    <main class="flex-grow-1 p-3 p-md-4 overflow-auto">
                        @isset($breadcrumbs)
                            <x-admin.breadcrumb :items="$breadcrumbs" />
                        @endisset

                        @isset($header)
                            <div class="mb-3">
                                {{ $header }}
                            </div>
                        @endisset

                        @if (session('status'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                {{ session('status') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        {{ $slot }}
                    </main>

                    <footer class="bg-body-tertiary border-top py-3 px-3 px-md-4 mt-auto">
                        <div class="d-flex flex-wrap justify-content-between align-items-center small text-secondary">
                            <span>© 2026 HANDOURA Houcine — Tous droits réservés</span>
                            <span class="fw-semibold">Version 1.0</span>
                        </div>
                    </footer>
                </div>
            </div>
        </div>

        <div class="admin-sidebar-overlay" id="sidebarOverlay"></div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const sidebar = document.querySelector('.admin-sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                const toggleBtn = document.getElementById('mobileMenuToggle');

                if (toggleBtn && sidebar) {
                    toggleBtn.addEventListener('click', function () {
                        sidebar.classList.toggle('open');
                        overlay.classList.toggle('open');
                    });

                    overlay.addEventListener('click', function () {
                        sidebar.classList.remove('open');
                        overlay.classList.remove('open');
                    });
                }
            });
        </script>

        @stack('scripts')
    </body>
</html>
