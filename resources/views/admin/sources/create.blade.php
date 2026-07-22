<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Nouvelle source') }}</h2>
    </x-slot>

    <div class="card" style="max-width: 40rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.sources.store') }}">
                @csrf
                <x-admin.sources.form :banks="$banks" :currencies="$currencies" />
            </form>
        </div>
    </div>
</x-app-layout>
