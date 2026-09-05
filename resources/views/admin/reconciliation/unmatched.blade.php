<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Transactions non rapprochées par fichier importé') }}</h2>
    </x-slot>

    <form method="GET" action="{{ route('admin.reconciliation.unmatched') }}" class="card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="import_a_id" class="form-label small fw-semibold">{{ __('Fichier Source A') }}</label>
                    <select name="import_a_id" id="import_a_id" class="form-select">
                        <option value="">{{ __('Sélectionner...') }}</option>
                        @foreach ($sources as $source)
                            @php $imports = $source->imports()->where('status', 'completed')->orderByDesc('created_at')->get(); @endphp
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
                                    @php $imports = $source->imports()->where('status', 'completed')->orderByDesc('created_at')->get(); @endphp
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
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header fw-semibold">
                            {{ __('Transactions dans') }} {{ $sourceAName }} {{ __('sans correspondance dans') }} {{ $sourceBName }}
                            <span class="badge bg-secondary ms-2">{{ $unmatchedA->count() }}</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('Source') }}</th>
                                        <th>{{ __('Référence') }}</th>
                                        <th>{{ __('Montant') }}</th>
                                        <th>{{ __('Date') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($unmatchedA as $tx)
                                        <tr>
                                            <td>{{ $tx['source'] ?? 'N/A' }}</td>
                                            <td>{{ $tx['reference'] ?? ($tx['primary_key_value'] ?? '') }}</td>
                                            <td>{{ $tx['amount_millimes'] ?? '' }}</td>
                                            <td>{{ $tx['date'] ?? '' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-secondary py-3">{{ __('Aucune transaction sans correspondance.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header fw-semibold">
                            {{ __('Transactions dans') }} {{ $sourceBName }} {{ __('sans correspondance dans') }} {{ $sourceAName }}
                            <span class="badge bg-secondary ms-2">{{ $unmatchedB->count() }}</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('Source') }}</th>
                                        <th>{{ __('Référence') }}</th>
                                        <th>{{ __('Montant') }}</th>
                                        <th>{{ __('Date') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($unmatchedB as $tx)
                                        <tr>
                                            <td>{{ $tx['source'] ?? 'N/A' }}</td>
                                            <td>{{ $tx['reference'] ?? ($tx['primary_key_value'] ?? '') }}</td>
                                            <td>{{ $tx['amount_millimes'] ?? '' }}</td>
                                            <td>{{ $tx['date'] ?? '' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-secondary py-3">{{ __('Aucune transaction sans correspondance.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @elseif ($importAId !== null && $importBId !== null && $importAId === $importBId)
        <div class="alert alert-warning">{{ __('Veuillez sélectionner deux fichiers différents.') }}</div>
    @endif
</x-app-layout>
