<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Modifier la devise') }}</h2>
    </x-slot>

    <div class="card" style="max-width: 40rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.currencies.update', $currency) }}">
                @csrf
                @method('PUT')
                <x-admin.currencies.form :currency="$currency" />
            </form>
        </div>
    </div>
</x-app-layout>
