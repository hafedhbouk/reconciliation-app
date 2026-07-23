<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Nouvelle règle de rapprochement') }}</h2>
    </x-slot>

    <div class="card" style="max-width: 50rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.matching-rules.store') }}">
                @csrf
                <x-admin.matching-rules.form :sources="$sources" />
            </form>
        </div>
    </div>
</x-app-layout>
