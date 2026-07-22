<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Modifier la banque') }}</h2>
    </x-slot>

    <div class="card" style="max-width: 40rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.banks.update', $bank) }}">
                @csrf
                @method('PUT')
                <x-admin.banks.form :bank="$bank" />
            </form>
        </div>
    </div>
</x-app-layout>
