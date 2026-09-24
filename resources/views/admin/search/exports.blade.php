<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Mes exports de recherche') }}</h2>
    </x-slot>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>{{ __('Historique des exports') }}</span>
            <a href="{{ route('admin.search.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>{{ __('Retour à la recherche') }}
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Format') }}</th>
                            <th>{{ __('Statut') }}</th>
                            <th>{{ __('Filtres appliqués') }}</th>
                            <th>{{ __('Demandé le') }}</th>
                            <th>{{ __('Terminé le') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($exports as $export)
                            <tr>
                                <td><span class="badge bg-secondary">{{ strtoupper($export->format) }}</span></td>
                                <td>
                                    @switch($export->status)
                                        @case('pending')
                                            <span class="badge bg-info">{{ __('En attente') }}</span>
                                            @break
                                        @case('processing')
                                            <span class="badge bg-warning text-dark">{{ __('En cours') }}</span>
                                            @break
                                        @case('completed')
                                            <span class="badge bg-success">{{ __('Terminé') }}</span>
                                            @break
                                        @case('failed')
                                            <span class="badge bg-danger">{{ __('Échoué') }}</span>
                                            @break
                                    @endswitch
                                </td>
                                <td>
                                    <small>
                                        @if ($export->filters['source_id'] ?? null)
                                            {{ __('Source') }}: {{ \App\Models\Source::query()->find($export->filters['source_id'])?->code }}<br>
                                        @endif
                                        @if ($export->filters['reference'] ?? null)
                                            {{ __('Référence') }}: {{ $export->filters['reference'] }}<br>
                                        @endif
                                        @if ($export->filters['matching_status'] ?? null)
                                            {{ __('Statut') }}: {{ \App\Enums\MatchingStatus::tryFrom($export->filters['matching_status'])?->label() }}<br>
                                        @endif
                                        @if ($export->filters['canal'] ?? null)
                                            {{ __('Canal') }}: {{ $export->filters['canal'] }}<br>
                                        @endif
                                        @if ($export->filters['amount_min'] ?? null || $export->filters['amount_max'] ?? null)
                                            {{ __('Montant') }}: {{ $export->filters['amount_min'] ?? 0 }} - {{ $export->filters['amount_max'] ?? '∞' }}<br>
                                        @endif
                                        @if ($export->filters['date_from'] ?? null || $export->filters['date_to'] ?? null)
                                            {{ __('Période') }}: {{ $export->filters['date_from'] ?? '∞' }} - {{ $export->filters['date_to'] ?? '∞' }}
                                        @endif
                                    </small>
                                </td>
                                <td>{{ $export->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $export->completed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td>
                                    @if ($export->isCompleted())
                                        <a href="{{ route('admin.search.exports.download', $export->download_token) }}" class="btn btn-sm btn-primary">
                                            <i class="bi bi-download me-1"></i>{{ __('Télécharger') }}
                                        </a>
                                    @elseif ($export->isFailed())
                                        <button class="btn btn-sm btn-outline-danger" disabled title="{{ $export->error_message }}">
                                            <i class="bi bi-x-circle me-1"></i>{{ __('Échec') }}
                                        </button>
                                    @else
                                        <span class="text-muted small">{{ __('En cours...') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-secondary py-4">{{ __('Aucun export trouvé.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $exports->links() }}
        </div>
    </div>
</x-app-layout>