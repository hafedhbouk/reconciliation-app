{{--
    Page « Mes opérations » : imports, rapprochements, comparaisons et exports, avec leur progression réelle.
    Le tableau est construit et mis à jour par resources/js/operations.js. Données : OperationController::index.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ $all ? __('Toutes les opérations') : __('Mes opérations') }}</h2>
    </x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="text-secondary small mb-0">
            {{ __('Avancement réel des traitements lancés, mis à jour automatiquement. Les opérations « En attente » démarrent dès qu’un worker de file d’attente est libre.') }}
        </p>
        @if ($canViewAll)
            <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Périmètre') }}">
                <a href="{{ route('admin.operations.index') }}" class="btn btn-outline-secondary {{ $all ? '' : 'active' }}">{{ __('Mes opérations') }}</a>
                <a href="{{ route('admin.operations.index', ['all' => 1]) }}" class="btn btn-outline-secondary {{ $all ? 'active' : '' }}">{{ __('Toutes') }}</a>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0"
                   data-operations-list
                   data-url="{{ route('admin.operations.data', $all ? ['all' => 1] : []) }}"
                   data-operations="{{ json_encode($operations) }}"
                   data-show-user="{{ $all ? '1' : '0' }}">
                <thead>
                    <tr>
                        <th>{{ __('Opération') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th>{{ __('Progression') }}</th>
                        <th>{{ __('Démarrée le') }}</th>
                        @if ($all)
                            <th>{{ __('Utilisateur') }}</th>
                        @endif
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Rempli par resources/js/operations.js. --}}
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
