<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="d-flex flex-column gap-3" style="max-width: 40rem;">
        <div class="card">
            <div class="card-body">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="card border-danger">
            <div class="card-body">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>
