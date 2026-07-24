<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

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
        <div class="d-flex flex-column justify-content-center align-items-center vh-100 bg-body-secondary">
            <div class="mb-3">
                <a href="/" class="text-decoration-none">
                    <x-application-logo style="height: 4rem; width: auto;" />
                </a>
            </div>

            <div class="card shadow-sm" style="width: 100%; max-width: 28rem;">
                <div class="card-body p-4">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
