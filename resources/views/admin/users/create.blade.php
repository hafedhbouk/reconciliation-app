<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Nouvel utilisateur') }}</h2>
    </x-slot>

    <div class="card" style="max-width: 40rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf
                <x-admin.users.form :roles="$roles" />
            </form>
        </div>
    </div>
</x-app-layout>
