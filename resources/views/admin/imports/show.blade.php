<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Import') }} — {{ $import->original_filename }}</h2>
    </x-slot>

    @if ($mappingState !== 'current')
        <div class="alert alert-warning">
            {{ $mappingState === 'unknown' ? __('Version du mapping non enregistrée : vérifier les anciens imports avant rapprochement.') : __('Le mapping a changé depuis cet import. Une renormalisation est nécessaire pour appliquer les nouvelles règles.') }}
        </div>
    @endif
    <p class="small text-secondary">{{ __('Version du mapping utilisée') }} : {{ $import->mapping_hash ? substr($import->mapping_hash, 0, 12) : __('Non enregistrée') }}</p>

    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-2">{{ __('Source') }}</dt>
                <dd class="col-sm-4">{{ $import->source?->code }} — {{ $import->source?->name }}</dd>

                <dt class="col-sm-2">{{ __('Statut') }}</dt>
                <dd class="col-sm-4">
                    <span class="badge {{ $import->status->badgeClass() }}">{{ $import->status->label() }}</span>
                </dd>

                <dt class="col-sm-2">{{ __('Importé par') }}</dt>
                <dd class="col-sm-4">{{ $import->importedByUser?->name ?? '—' }}</dd>

                <dt class="col-sm-2">{{ __('Lignes lues') }}</dt>
                <dd class="col-sm-4">{{ $import->total_rows ?? '—' }}</dd>

                <dt class="col-sm-2">{{ __('Lignes réussies') }}</dt>
                <dd class="col-sm-4">{{ $import->success_rows }}</dd>

                <dt class="col-sm-2">{{ __('Lignes en erreur') }}</dt>
                <dd class="col-sm-4">{{ $import->error_rows }}</dd>

                @if ($import->error_summary)
                    <dt class="col-sm-2">{{ __('Erreur') }}</dt>
                    <dd class="col-sm-10 text-danger">{{ $import->error_summary }}</dd>
                @endif
            </dl>

            @if ($import->status->value === 'pending' && ! $import->job_dispatched_at)
                <form method="POST" action="{{ route('admin.imports.process', $import) }}" class="mt-3 d-inline">
                    @csrf
                    <x-primary-button>{{ __('Lancer l\'import') }}</x-primary-button>
                </form>
            @elseif ($import->status->value === 'pending')
                <p class="text-secondary small mt-3 mb-0">
                    {{ __('Import en file d\'attente — en attente du worker de traitement.') }}
                </p>
            @endif

            @if ($canResume)
                <form method="POST" action="{{ route('admin.imports.process', $import) }}" class="mt-3 d-inline">
                    @csrf
                    <x-primary-button>{{ __('Reprendre les lignes non traitées') }}</x-primary-button>
                </form>
                <p class="small text-secondary mt-2">{{ __('Les lignes déjà enregistrées, y compris les rejets, sont conservées. La reprise utilise le mapping initial.') }}</p>
            @endif

            @can('delete', $import)
                <form method="POST" action="{{ route('admin.imports.destroy', $import) }}" class="mt-3 d-inline" onsubmit="return confirm('{{ __('Êtes-vous sûr de vouloir supprimer cet import ? Cette action est irréversible.') }}');">
                    @csrf
                    @method('DELETE')
                    <x-danger-button>{{ __('Supprimer l\'import') }}</x-danger-button>
                </form>
            @endcan
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">{{ __('Bilan du fichier') }}</div>
        <div class="card-body">
            <p>{{ __('Lues') }} : {{ $import->processed_rows }} · {{ __('Acceptées') }} : {{ $import->success_rows }} · {{ __('Rejetées') }} : {{ $import->error_rows }}</p>
            <p>{{ __('Montant total accepté (millimes)') }} : {{ $ledger->sum('amount_millimes') }}</p>
            @php $balancedImport = $import->processed_rows === $import->success_rows + $import->error_rows && $import->success_rows === (int) $ledger->sum('rows_count'); @endphp
            <p class="{{ $balancedImport ? 'text-success' : 'text-danger' }}">{{ $balancedImport ? __('Contrôle des compteurs : équilibré.') : __('Écart entre les compteurs et les transactions enregistrées.') }}</p>
            <p class="small text-secondary mb-0">{{ __('Les montants des lignes rejetées ne sont pas inclus : ils peuvent être invalides ou absents. Les conflits et exclusives se lisent par comparaison ci-dessous.') }}</p>
        </div>
    </div>

    @foreach ($runs as $run)
        @php $side = $run->import_a_id === $import->id ? 'a' : 'b'; $other = $side === 'a' ? $run->importB : $run->importA; @endphp
        @if ($run->file_totals)
            <div class="card mb-3">
                <div class="card-header">{{ __('Comparaison avec') }} {{ $other?->original_filename }} · {{ $run->created_at->format('d/m/Y H:i:s') }} · {{ __('Lot') }} {{ $run->batch_reference }}</div>
                @if ($run->invalidated_at)<p class="text-warning m-2">{{ __('Bilan historique antérieur à une renormalisation. Relancer le rapprochement pour un bilan actuel.') }}</p>@endif
                @include('admin.reconciliation._file-totals', ['totals' => $run->file_totals[$side]])
            </div>
        @endif
    @endforeach
    {{ $runs->links() }}

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">{{ __('Détail des lignes') }}</span>
            <div class="btn-group btn-group-sm">
                <a href="{{ route('admin.imports.show', $import) }}" class="btn btn-outline-secondary {{ request('status') ? '' : 'active' }}">{{ __('Toutes') }}</a>
                <a href="{{ route('admin.imports.show', ['import' => $import, 'status' => 'error']) }}" class="btn btn-outline-danger {{ request('status') === 'error' ? 'active' : '' }}">{{ __('Erreurs') }}</a>
                <a href="{{ route('admin.imports.show', ['import' => $import, 'status' => 'imported']) }}" class="btn btn-outline-success {{ request('status') === 'imported' ? 'active' : '' }}">{{ __('Importées') }}</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Ligne') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th>{{ __('Erreur') }}</th>
                        <th>{{ __('Détail') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row->row_number }}</td>
                            <td>
                                <span class="badge {{ $row->status->value === 'error' ? 'bg-danger' : 'bg-success' }}">
                                    {{ $row->status->label() }}
                                </span>
                            </td>
                            <td class="small text-danger">{{ $row->error_message }}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#row-{{ $row->id }}">
                                    <i class="bi bi-code-square"></i>
                                </button>
                            </td>
                        </tr>
                        <tr class="collapse" id="row-{{ $row->id }}">
                            <td colspan="4">
                                <strong>{{ __('Données brutes') }}</strong>
                                <pre class="bg-body-tertiary p-2 rounded small">{{ json_encode($row->raw_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                @if ($row->transformed_data)
                                    <strong>{{ __('Données transformées') }}</strong>
                                    <pre class="bg-body-tertiary p-2 rounded small">{{ json_encode($row->transformed_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                @endif
                                @if ($row->normalized_data)
                                    <strong>{{ __('Données normalisées') }}</strong>
                                    <pre class="bg-body-tertiary p-2 rounded small">{{ json_encode($row->normalized_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-secondary py-4">{{ __('Aucune ligne à afficher.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="card-footer">
                {{ $rows->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
