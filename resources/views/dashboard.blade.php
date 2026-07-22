<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="card">
        <div class="card-body">
            {{ __("You're logged in!") }}
        </div>
    </div>
</x-app-layout>
