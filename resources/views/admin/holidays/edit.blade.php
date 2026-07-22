<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Modifier le jour férié') }}</h2>
    </x-slot>

    <div class="card" style="max-width: 40rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.holidays.update', $holiday) }}">
                @csrf
                @method('PUT')
                <x-admin.holidays.form :holiday="$holiday" />
            </form>
        </div>
    </div>
</x-app-layout>
