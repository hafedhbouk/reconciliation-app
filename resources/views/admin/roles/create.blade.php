<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Nouveau rôle') }}</h2>
    </x-slot>

    <div class="card" style="max-width: 48rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.roles.store') }}">
                @csrf
                <x-admin.roles.form :permissions="$permissions" />
            </form>
        </div>
    </div>
</x-app-layout>
