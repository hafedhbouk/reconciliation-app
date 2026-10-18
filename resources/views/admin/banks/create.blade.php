{{--
    Page « Nouvelle banque ». Données fournies par BankController::create.
    Le formulaire lui-même est le composant <x-admin.banks.form> (components/admin/banks/form.blade.php).
--}}
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
