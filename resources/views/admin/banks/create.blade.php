<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Nouvelle banque') }}</h2>
    </x-slot>

    <div class="card" style="max-width: 40rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.banks.store') }}">
                @csrf
                <x-admin.banks.form />
            </form>
        </div>
    </div>
</x-app-layout>
