<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Transactions non rapprochées par fichier importé') }}</h2>
    </x-slot>

    @if (session('export_error'))
        <div class="alert alert-warning">{{ session('export_error') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.reconciliation.unmatched') }}" class="card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="import_a_id" class="form-label small fw-semibold">{{ __('Fichier Source A') }}</label>
                    <select name="import_a_id" id="import_a_id" class="form-select">
                        <option value="">{{ __('Sélectionner...') }}</option>
                        @foreach ($sources as $source)
                            @php $imports = $source->imports; @endphp
                            @if ($imports->isNotEmpty())
                                <optgroup label="{{ $source->name }} ({{ $source->code }})">
                                    @foreach ($imports as $import)
                                        <option value="{{ $import->id }}" {{ (string) $importAId === (string) $import->id ? 'selected' : '' }}>
                                            {{ $import->original_filename }} — {{ $import->created_at->format('d/m/Y H:i') }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="import_b_id" class="form-label small fw-semibold">{{ __('Fichier Source B') }}</label>
                    <select name="import_b_id" id="import_b_id" class="form-select">
                        <option value="">{{ __('Sélectionner...') }}</option>
                        @foreach ($sources as $source)
                                    @php $imports = $source->imports; @endphp
                                    @if ($imports->isNotEmpty())
                                        <optgroup label="{{ $source->name }} ({{ $source->code }})">
                                            @foreach ($imports as $import)
                                                <option value="{{ $import->id }}" {{ (string) $importBId === (string) $import->id ? 'selected' : '' }}>
                                                    {{ $import->original_filename }} — {{ $import->created_at->format('d/m/Y H:i') }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-dark w-100">
                        <i class="bi bi-funnel me-1"></i>{{ __('Filtrer') }}
                    </button>
                </div>
            </div>
        </div>
    </form>

    @if ($importAId !== null && $importBId !== null && $importAId !== $importBId && $snapshot)
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                @if ($snapshot->status === 'processing')
                    <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>{{ __('En cours') }}</span>
                @elseif ($snapshot->status === 'pending')
                    <span class="badge bg-info"><i class="bi bi-clock me-1"></i>{{ __('En attente') }}</span>
                @elseif ($snapshot->status === 'completed')
                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>{{ __('Terminé') }}</span>
                    <span class="text-secondary ms-2">{{ __('Calculé le') }} {{ $snapshot->completed_at?->format('d/m/Y H:i:s') }}</span>
                @elseif ($snapshot->status === 'failed')
                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>{{ __('Échoué') }}</span>
                @endif
            </div>
            @if ($snapshot->status === 'completed' || $snapshot->status === 'failed')
                <form method="POST" action="{{ route('admin.reconciliation.unmatched.refresh', ['import_a_id' => $importAId, 'import_b_id' => $importBId]) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-clockwise me-1"></i>{{ __('Relancer la comparaison') }}
                    </button>
                </form>
            @endif
        </div>
    @endif

    @if ($importAId !== null && $importBId !== null && $importAId !== $importBId)
        @php
            $importA = \App\Models\Import::query()->find($importAId);
            $importB = \App\Models\Import::query()->find($importBId);
            $sourceAName = $importA?->source?->name ?? 'A';
            $sourceBName = $importB?->source?->name ?? 'B';
            $sourceACode = strtoupper($importA?->source?->code ?? '');
            $sourceBCode = strtoupper($importB?->source?->code ?? '');
            $isAlphaBna = in_array($sourceACode, ['ALPHA', 'BNA'], true)
                && in_array($sourceBCode, ['ALPHA', 'BNA'], true)
                && $sourceACode !== $sourceBCode;
            $isAlphaWeb = in_array('ALPHA', [$sourceACode, $sourceBCode], true)
                && (in_array('WEB', [$sourceACode, $sourceBCode], true) || in_array('STEG', [$sourceACode, $sourceBCode], true));
            $isBnaWeb = in_array('BNA', [$sourceACode, $sourceBCode], true)
                && (in_array('WEB', [$sourceACode, $sourceBCode], true) || in_array('STEG', [$sourceACode, $sourceBCode], true));
            $columnsFor = static function (string $sourceCode) use ($isAlphaBna, $isAlphaWeb, $isBnaWeb) {
                if ($isAlphaBna) {
                    return $sourceCode === 'ALPHA'
                        ? [
                            ['label' => 'NUM_AUTO', 'field' => 'num_autorisation'],
                            ['label' => 'DAT_ENC', 'field' => 'date'],
                            ['label' => 'MONTANT_ENCAISS', 'field' => 'amount_millimes'],
                        ]
                        : [
                            ['label' => 'N° autorisation', 'field' => 'num_autorisation'],
                            ['label' => 'Date', 'field' => 'date'],
                            ['label' => 'Montant', 'field' => 'amount_millimes'],
                        ];
                }

                if ($isAlphaWeb) {
                    return $sourceCode === 'ALPHA'
                        ? [
                            ['label' => 'REFERENCE', 'field' => 'reference'],
                            ['label' => 'NUM_AUTO', 'field' => 'num_autorisation'],
                            ['label' => 'DAT_ENC', 'field' => 'date'],
                            ['label' => 'MONTANT_ENCAISS', 'field' => 'amount_millimes'],
                        ]
                        : [
                            ['label' => 'reference', 'field' => 'reference'],
                            ['label' => 'recu_paie', 'field' => 'secondary_reference'],
                            ['label' => 'date_paiement', 'field' => 'date'],
                            ['label' => 'montant', 'field' => 'amount_millimes'],
                        ];
                }

                if ($isBnaWeb) {
                    return $sourceCode === 'BNA'
                        ? [
                            ['label' => 'N° autorisation', 'field' => 'num_autorisation'],
                            ['label' => 'Date', 'field' => 'date'],
                            ['label' => 'Montant', 'field' => 'amount_millimes'],
                        ]
                        : [
                            ['label' => 'recu_paie', 'field' => 'secondary_reference'],
                            ['label' => 'date_paiement', 'field' => 'date'],
                            ['label' => 'montant', 'field' => 'amount_millimes'],
                        ];
                }

                return [
                    ['label' => __('Source'), 'field' => 'source'],
                    ['label' => __('Référence'), 'field' => 'reference'],
                    ['label' => __('Montant'), 'field' => 'amount_millimes'],
                    ['label' => __('Date'), 'field' => 'date'],
                ];
            };
            $columnsA = $columnsFor($sourceACode);
            $columnsB = $columnsFor($sourceBCode);
            $valueFor = static fn (array $row, string $field) => match ($field) {
                'source' => $row['source'] ?? 'N/A',
                'reference' => $row['reference'] ?? ($row['primary_key_value'] ?? ''),
                'num_autorisation' => $row['num_autorisation'] ?? '—',
                'secondary_reference' => $row['secondary_reference'] ?? '—',
                default => $row[$field] ?? '',
            };
        @endphp

        @if ($snapshot && $snapshot->status === 'processing')
            <div class="alert alert-warning">
                <i class="bi bi-hourglass-split me-1"></i>
                {{ __('Comparaison en cours de traitement, veuillez patienter...') }}
            </div>
        @elseif ($snapshot && $snapshot->status === 'pending')
            <div class="alert alert-info">
                <i class="bi bi-hourglass-split me-1"></i>
                {{ __('Comparaison en attente de traitement...') }}
            </div>
        @elseif ($snapshot && $snapshot->status === 'failed')
            <div class="alert alert-danger">
                <i class="bi bi-x-circle me-1"></i>
                {{ __('Erreur lors du traitement : :error', ['error' => $snapshot->error]) }}
            </div>
        @endif

        @if ($snapshot && $snapshot->status === 'completed')
            <div class="mb-3">
                <form method="POST" action="{{ route('admin.reconciliation.unmatched.export-async', $snapshot) }}" class="d-flex align-items-center gap-2">
                    @csrf
                    <label for="unmatched_export_format" class="visually-hidden">{{ __('Format') }}</label>
                    <select name="format" id="unmatched_export_format" class="form-select form-select-sm w-auto">
                        <option value="xlsx">Excel (XLSX)</option>
                        <option value="csv">CSV</option>
                        <option value="pdf">PDF</option>
                    </select>
                    <button type="submit" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-download me-1"></i>{{ __('Lancer l’export') }}
                    </button>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.matching-results.exports') }}">
                        <i class="bi bi-folder2-open me-1"></i>{{ __('Mes exports') }}
                    </a>
                </form>
                <small class="text-secondary">{{ __('Les exports regroupent les différences des deux fichiers, toutes pages confondues. Excel et PDF : 1 000 lignes maximum ; CSV : toutes les lignes. Montants en millimes.') }}</small>
            </div>
            @if ($snapshot->file_totals)
                <div class="row mb-3">
                    @foreach (['a' => $sourceAName, 'b' => $sourceBName] as $side => $name)
                        <div class="col-md-6"><div class="card">
                            <div class="card-header">{{ __('Bilan') }} — {{ $name }}</div>
                            @include('admin.reconciliation._file-totals', ['totals' => $snapshot->file_totals[$side]])
                        </div></div>
                    @endforeach
                </div>
            @endif
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header fw-semibold">
                            {{ __('Transactions dans') }} {{ $sourceAName }} {{ __('sans correspondance dans') }} {{ $sourceBName }}
                            <span class="badge bg-secondary ms-2">{{ $unmatchedA->total() }}</span>
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
                                    @forelse ($unmatchedA as $tx)
                                        <tr>
                                            @foreach ($columnsA as $column)
                                                <td>{{ $valueFor($tx, $column['field']) }}</td>
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
                            {{ __('Transactions dans') }} {{ $sourceBName }} {{ __('sans correspondance dans') }} {{ $sourceAName }}
                            <span class="badge bg-secondary ms-2">{{ $unmatchedB->total() }}</span>
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
                                    @forelse ($unmatchedB as $tx)
                                        <tr>
                                            @foreach ($columnsB as $column)
                                                <td>{{ $valueFor($tx, $column['field']) }}</td>
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
        @endif
    @elseif ($importAId !== null && $importBId !== null && $importAId === $importBId)
        <div class="alert alert-warning">{{ __('Veuillez sélectionner deux fichiers différents.') }}</div>
    @endif
</x-app-layout>
