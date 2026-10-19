{{--
    Écarts entre deux fichiers : choix des fichiers A et B, état du calcul (en cours / terminé),
    lignes présentes d'un seul côté et exports. Données : ReconciliationController::unmatched.
--}}
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
            // Colonnes de chaque tableau, valeurs et mention « même clé dans l'autre fichier » :
            // logique partagée avec l'export des écarts (voir app/Services/Matching/DifferenceColumns.php).
            $table = new \App\Services\Matching\DifferenceColumns($sourceACode, $sourceBCode);
            $columnsA = $table->columns($sourceACode);
            $columnsB = $table->columns($sourceBCode);
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
                                        @php $note = $table->sameKeyNote($tx['same_key'] ?? null, $sourceAName, $sourceBName, $sourceBCode); @endphp
                                        <tr>
                                            @foreach ($columnsA as $column)
                                                <td @class(['border-bottom-0' => $note])>{{ $table->value($tx, $column['field']) }}</td>
                                            @endforeach
                                        </tr>
                                        @if ($note)
                                            <tr>
                                                <td colspan="{{ count($columnsA) }}" class="small text-secondary pt-0">↳ {{ $note }}</td>
                                            </tr>
                                        @endif
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
                                        @php $note = $table->sameKeyNote($tx['same_key'] ?? null, $sourceBName, $sourceAName, $sourceACode); @endphp
                                        <tr>
                                            @foreach ($columnsB as $column)
                                                <td @class(['border-bottom-0' => $note])>{{ $table->value($tx, $column['field']) }}</td>
                                            @endforeach
                                        </tr>
                                        @if ($note)
                                            <tr>
                                                <td colspan="{{ count($columnsB) }}" class="small text-secondary pt-0">↳ {{ $note }}</td>
                                            </tr>
                                        @endif
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
