<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Exports de rapprochement') }}</h2>
    </x-slot>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('ID') }}</th>
                        <th>{{ __('Format') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th>{{ __('Filtres') }}</th>
                        <th>{{ __('Lancé le') }}</th>
                        <th>{{ __('Terminé le') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($exports as $export)
                        <tr>
                            <td>#{{ $export->id }}</td>
                            <td>
                                <span class="badge bg-secondary text-uppercase">{{ $export->format }}</span>
                            </td>
                            <td>
                                @switch($export->status)
                                    @case('completed')
                                        <span class="badge bg-success">{{ __('Terminé') }}</span>
                                        @break
                                    @case('processing')
                                        <span class="badge bg-info">{{ __('En cours') }}</span>
                                        @break
                                    @case('failed')
                                        <span class="badge bg-danger">{{ __('Échoué') }}</span>
                                        @break
                                    @default
                                        <span class="badge bg-secondary">{{ __('En attente') }}</span>
                                @endswitch
                            </td>
                            <td>
                                @if ($export->filters)
                                    <small class="text-muted">
                                        @foreach ($export->filters as $key => $value)
                                            @if ($value)
                                                {{ $key }}: {{ $value }}<br>
                                            @endif
                                        @endforeach
                                    </small>
                                @else
                                    <span class="text-muted">{{ __('Tous') }}</span>
                                @endif
                            </td>
                            <td>{{ $export->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $export->completed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>
                                @if ($export->isCompleted())
                                    <a href="{{ route('admin.matching-results.exports.download', $export->download_token) }}" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-download"></i> {{ __('Télécharger') }}
                                    </a>
                                @elseif ($export->isFailed() && $export->error_message)
                                    <span class="text-danger small" title="{{ $export->error_message }}">
                                        <i class="bi bi-exclamation-triangle"></i> {{ __('Erreur') }}
                                    </span>
                                @else
                                    <span class="text-muted small">{{ __('En attente du worker...') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-4">{{ __('Aucun export pour le moment.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($exports->hasPages())
        <div class="mt-3">
            {{ $exports->links() }}
        </div>
    @endif
</x-app-layout>
