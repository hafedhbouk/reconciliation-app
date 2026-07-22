<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Nouveau jour férié') }}</h2>
    </x-slot>

    <div class="card" style="max-width: 40rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.holidays.store') }}">
                @csrf
                <x-admin.holidays.form />
            </form>
        </div>
    </div>
</x-app-layout>
