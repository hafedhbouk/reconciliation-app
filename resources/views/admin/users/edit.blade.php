<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Modifier l\'utilisateur') }}</h2>
    </x-slot>

    <div class="card" style="max-width: 40rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf
                @method('PUT')
                <x-admin.users.form :user="$user" :roles="$roles" />
            </form>
        </div>
    </div>
</x-app-layout>
