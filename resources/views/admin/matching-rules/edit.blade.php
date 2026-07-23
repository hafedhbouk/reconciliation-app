<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Modifier la règle de rapprochement') }}</h2>
    </x-slot>

    <div class="card" style="max-width: 50rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.matching-rules.update', $rule) }}">
                @csrf
                @method('PUT')
                <x-admin.matching-rules.form :rule="$rule" :sources="$sources" />
            </form>
        </div>
    </div>
</x-app-layout>
