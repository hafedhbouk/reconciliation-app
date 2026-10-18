{{--
    Composant <x-auth-session-status> : message de succès des pages de connexion (Laravel Breeze).
--}}
@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'text-success small fw-medium mb-3']) }}>
        {{ $status }}
    </div>
@endif
