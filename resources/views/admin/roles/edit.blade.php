<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Modifier le rôle') }}</h2>
    </x-slot>

    <div class="card" style="max-width: 48rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.roles.update', $role) }}">
                @csrf
                @method('PUT')
                <x-admin.roles.form :role="$role" :permissions="$permissions" />
            </form>
        </div>
    </div>
</x-app-layout>
