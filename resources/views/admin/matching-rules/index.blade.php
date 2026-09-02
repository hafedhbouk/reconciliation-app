<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fs-4 fw-semibold mb-0">{{ __('Règles de rapprochement') }}</h2>
            <div class="d-flex gap-2">
                @can('matching-rules.update')
                    <form action="{{ route('admin.matching-rules.run-all') }}" method="POST" onsubmit="return confirm('Lancer toutes les règles actives, dans l\'ordre de priorité ?')">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="bi bi-play-circle me-1"></i>{{ __('Lancer tout') }}
                        </button>
                    </form>
                    <form action="{{ route('admin.matching-rules.detect-duplicates') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-files me-1"></i>{{ __('Détecter les doublons') }}
                        </button>
                    </form>
                    <form action="{{ route('admin.matching-rules.sweep-unmatched') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-broom me-1"></i>{{ __('Balayer les non-rapprochés') }}
                        </button>
                    </form>
                @endcan
                @can('matching-rules.create')
                    <a href="{{ route('admin.matching-rules.create') }}" class="btn btn-dark btn-sm">
                        <i class="bi bi-plus-lg me-1"></i>{{ __('Nouvelle règle') }}
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    @can('matching-rules.update')
        <div class="card mb-3">
            <div class="card-header fw-semibold">{{ __('Lancer un rapprochement personnalisé') }}</div>
            <div class="card-body">
                <form action="{{ route('admin.matching-rules.run-ad-hoc') }}" method="POST" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-auto">
                        <label for="source_a_id" class="form-label small fw-semibold">{{ __('Source A') }}</label>
                        <select name="source_a_id" id="source_a_id" class="form-select form-select-sm" required>
                            <option value="">{{ __('Sélectionner...') }}</option>
                            @foreach ($sources as $source)
                                <option value="{{ $source->id }}">{{ $source->name }} ({{ $source->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <label for="source_b_id" class="form-label small fw-semibold">{{ __('Source B') }}</label>
                        <select name="source_b_id" id="source_b_id" class="form-select form-select-sm" required>
                            <option value="">{{ __('Sélectionner...') }}</option>
                            @foreach ($sources as $source)
                                <option value="{{ $source->id }}">{{ $source->name }} ({{ $source->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-play-circle me-1"></i>{{ __('Démarrer le rapprochement') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <div class="card">
        <div class="table-responsive">
            <table id="matching-rules-table" class="table table-hover mb-0 align-middle w-100">
                <thead>
                    <tr>
                        <th>{{ __('Nom') }}</th>
                        <th>{{ __('Source A') }}</th>
                        <th>{{ __('Source B') }}</th>
                        <th>{{ __('Cardinalité') }}</th>
                        <th>{{ __('Priorité') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</x-app-layout>
