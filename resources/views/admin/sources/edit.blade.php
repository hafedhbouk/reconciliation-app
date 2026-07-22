<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Modifier la source') }}</h2>
    </x-slot>

    <div class="card" style="max-width: 40rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.sources.update', $source) }}">
                @csrf
                @method('PUT')
                <x-admin.sources.form :source="$source" :banks="$banks" :currencies="$currencies" />
            </form>
        </div>
    </div>
</x-app-layout>
