{{--
    Page de connexion (Laravel Breeze). Envoyée à AuthenticatedSessionController::store.
--}}
<x-guest-layout>
    <div class="mb-4">
        <h2 class="fs-4 fw-bold mb-1">{{ __('Bienvenue') }}</h2>
        <p class="text-secondary small mb-0">{{ __('Connectez-vous pour accéder à la plateforme de rapprochement.') }}</p>
    </div>

    <x-auth-session-status :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="mb-3">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div class="form-check mb-0">
                <input id="remember_me" type="checkbox" class="form-check-input" name="remember">
                <label class="form-check-label small" for="remember_me">{{ __('Remember me') }}</label>
            </div>
            @if (Route::has('password.request'))
                <a class="small fw-semibold" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <x-primary-button class="w-100 py-2">
            <i class="bi bi-box-arrow-in-right me-1"></i>{{ __('Log in') }}
        </x-primary-button>
    </form>
</x-guest-layout>
