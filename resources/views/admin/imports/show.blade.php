<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Import') }} — {{ $import->original_filename }}</h2>
    </x-slot>

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

                <dt class="col-sm-2">{{ __('Total lignes') }}</dt>
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
                <form method="POST" action="{{ route('admin.imports.process', $import) }}" class="mt-3">
                    @csrf
                    <x-primary-button>{{ __('Lancer l\'import') }}</x-primary-button>
                </form>
            @elseif ($import->status->value === 'pending')
                <p class="text-secondary small mt-3 mb-0">
                    {{ __('Import en file d\'attente — en attente du worker de traitement.') }}
                </p>
            @endif
        </div>
    </div>

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
