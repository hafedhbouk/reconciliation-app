{{--
    Composant <x-operation-progress :operation="$operation" /> : progression réelle d'une opération
    (import, rapprochement, comparaison, export), mise à jour toutes les 2 s par resources/js/operations.js.
    :reload="true" recharge la page à la fin, pour afficher les résultats qui viennent d'être calculés.
--}}
@props(['operation', 'reload' => false])

<div class="card mb-3" data-operation-card>
    <div class="card-body py-2">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
            <span class="fw-semibold small">{{ $operation->label }}</span>
            <span data-operation-status></span>
        </div>
        <div data-operation-progress
             data-url="{{ route('admin.operations.show', $operation) }}"
             data-operation="{{ json_encode($operation->toProgressArray()) }}"
             data-reload-on-finish="{{ $reload ? '1' : '0' }}"></div>
    </div>
</div>
