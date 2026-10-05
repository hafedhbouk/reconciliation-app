{{-- Vue détail d'un résultat de rapprochement : focus sur les écarts --}}
<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fs-4 fw-semibold mb-0">{{ __('Résultat de rapprochement') }} #{{ $result->id }}</h2>
            @can('delete', $result)
                <form method="POST" action="{{ route('admin.matching-results.destroy', $result) }}" onsubmit="return confirm('{{ __('Êtes-vous sûr de vouloir supprimer ce résultat de rapprochement ?') }}');">
                    @csrf
                    @method('DELETE')
                    <x-danger-button>{{ __('Supprimer') }}</x-danger-button>
                </form>
            @endcan
        </div>
    </x-slot>

    @if ($result->comparisonRun?->invalidated_at)
        <div class="alert alert-warning">{{ __('Ces fichiers ont été renormalisés depuis cette exécution. Relancer le rapprochement pour vérifier les résultats actuels.') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-2">{{ __('Règle') }}</dt>
                <dd class="col-sm-4">{{ $result->matchingRule?->name ?? __('Rapprochement manuel') }}</dd>

                <dt class="col-sm-2">{{ __('Statut') }}</dt>
                <dd class="col-sm-4">
                    <span class="badge {{ $result->status->badgeClass() }}">{{ $result->status->label() }}</span>
                </dd>

                <dt class="col-sm-2">{{ __('Confiance') }}</dt>
                <dd class="col-sm-4">{{ $result->confidence_score ?? '—' }}</dd>

                <dt class="col-sm-2">{{ __('Traité par') }}</dt>
                <dd class="col-sm-4">{{ $result->matchedByUser?->name ?? __('Automatique') }}</dd>

                <dt class="col-sm-2">{{ __('Lot') }}</dt>
                <dd class="col-sm-4">{{ $result->batch_reference ?? '—' }}</dd>

                @if ($run = $result->comparisonRun)
                    <dt class="col-sm-2">{{ __('Fichiers comparés') }}</dt>
                    <dd class="col-sm-10">#{{ $run->import_a_id }} {{ $run->importA?->original_filename }} ↔ #{{ $run->import_b_id }} {{ $run->importB?->original_filename }}</dd>
                @endif

                @if ($result->notes)
                    <dt class="col-sm-2">{{ __('Notes') }}</dt>
                    <dd class="col-sm-10 text-warning-emphasis">{{ $result->notes }}</dd>
                @endif
            </dl>
        </div>
    </div>

    @php
        $unmatchedSourceA = $result->matchingRule?->sourceA;
        $unmatchedSourceB = $result->matchingRule?->sourceB;
        $columnsFor = static function ($source, $peer) {
            $sourceCode = strtoupper($source?->code ?? '') === 'STEG' ? 'WEB' : strtoupper($source?->code ?? '');
            $peerCode = strtoupper($peer?->code ?? '') === 'STEG' ? 'WEB' : strtoupper($peer?->code ?? '');

            if (in_array($sourceCode, ['BNA', 'WEB'], true)
                && in_array($peerCode, ['BNA', 'WEB'], true)) {
                return $sourceCode === 'BNA'
                    ? [
                        ['label' => 'N° autorisation', 'field' => 'authorization'],
                        ['label' => 'Date', 'field' => 'date'],
                        ['label' => 'Montant', 'field' => 'amount'],
                    ]
                    : [
                        ['label' => 'recu_paie', 'field' => 'secondary_reference'],
                        ['label' => 'date_paiement', 'field' => 'date'],
                        ['label' => 'montant', 'field' => 'amount'],
                    ];
            }

            if (in_array($sourceCode, ['ALPHA', 'WEB'], true)
                && in_array($peerCode, ['ALPHA', 'WEB'], true)) {
                return $sourceCode === 'ALPHA'
                    ? [
                        ['label' => 'REFERENCE', 'field' => 'reference'],
                        ['label' => 'NUM_AUTO', 'field' => 'authorization'],
                        ['label' => 'DAT_ENC', 'field' => 'date'],
                        ['label' => 'MONTANT_ENCAISS', 'field' => 'amount'],
                    ]
                    : [
                        ['label' => 'reference', 'field' => 'reference'],
                        ['label' => 'recu_paie', 'field' => 'secondary_reference'],
                        ['label' => 'date_paiement', 'field' => 'date'],
                        ['label' => 'montant', 'field' => 'amount'],
                    ];
            }

            return match ($sourceCode) {
                'ALPHA' => [
                    ['label' => 'NUM_AUTO', 'field' => 'authorization'],
                    ['label' => 'DAT_ENC', 'field' => 'date'],
                    ['label' => 'MONTANT_ENCAISS', 'field' => 'amount'],
                ],
                'BNA' => [
                    ['label' => 'N° autorisation', 'field' => 'authorization'],
                    ['label' => 'Date', 'field' => 'date'],
                    ['label' => 'Montant', 'field' => 'amount'],
                ],
                default => [
                    ['label' => __('Source'), 'field' => 'source'],
                    ['label' => __('Référence'), 'field' => 'reference'],
                    ['label' => __('Montant'), 'field' => 'amount'],
                    ['label' => __('Date'), 'field' => 'date'],
                ],
            };
        };
        $columnsA = $columnsFor($unmatchedSourceA, $unmatchedSourceB);
        $columnsB = $columnsFor($unmatchedSourceB, $unmatchedSourceA);
        $valueFor = static fn ($nt, $field) => match ($field) {
            'authorization' => $nt->transaction->raw_payload['num_autorisation'] ?? '—',
            'secondary_reference' => $nt->transaction->raw_payload['secondary_reference'] ?? '—',
            'date' => $nt->normalized_date?->format('d/m/Y') ?? '—',
            'amount' => $nt->normalized_amount_millimes,
            'reference' => $nt->normalized_reference,
            'source' => $nt->transaction->source->code,
            default => '—',
        };
    @endphp

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header fw-semibold">
                    {{ __('Transactions dans') }} {{ $unmatchedSourceA->name ?? 'A' }} {{ __('sans correspondance dans') }} {{ $unmatchedSourceB->name ?? 'B' }}
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                @foreach ($columnsA as $column)
                                    <th>{{ __($column['label']) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($unmatchedA as $nt)
                                <tr>
                                    @foreach ($columnsA as $column)
                                        <td>{{ $valueFor($nt, $column['field']) }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($columnsA) }}" class="text-center text-secondary py-3">{{ __('Aucune transaction sans correspondance.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">{{ $unmatchedA->links() }}</div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header fw-semibold">
                    {{ __('Transactions dans') }} {{ $unmatchedSourceB->name ?? 'B' }} {{ __('sans correspondance dans') }} {{ $unmatchedSourceA->name ?? 'A' }}
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                @foreach ($columnsB as $column)
                                    <th>{{ __($column['label']) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($unmatchedB as $nt)
                                <tr>
                                    @foreach ($columnsB as $column)
                                        <td>{{ $valueFor($nt, $column['field']) }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($columnsB) }}" class="text-center text-secondary py-3">{{ __('Aucune transaction sans correspondance.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">{{ $unmatchedB->links() }}</div>
            </div>
        </div>
    </div>

    @if ($result->exceptions->isNotEmpty())
        <div class="card">
            <div class="card-header fw-semibold">{{ __('Exceptions liées') }}</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Statut') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($result->exceptions as $exception)
                            <tr>
                                <td>{{ $exception->type->label() }}</td>
                                <td><span class="badge {{ $exception->status->badgeClass() }}">{{ $exception->status->label() }}</span></td>
                                <td>
                                    <a href="{{ route('admin.exceptions.show', $exception) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-app-layout>
