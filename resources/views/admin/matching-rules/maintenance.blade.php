<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fs-4 fw-semibold mb-0">{{ __('Contrôles après rapprochement') }}</h2>
            <a href="{{ route('admin.matching-rules.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Retour aux règles') }}</a>
        </div>
    </x-slot>

    <div class="card mb-3">
        <div class="card-header fw-semibold">{{ __('Périmètre des contrôles') }}</div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.matching-rules.maintenance') }}" class="row g-3 align-items-end">
                <input type="hidden" name="preview" value="1">
                <div class="col-md-3">
                    <label for="source_id" class="form-label">{{ __('Source') }}</label>
                    <select id="source_id" name="source_id" class="form-select">
                        <option value="">{{ __('Toutes les sources') }}</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->id }}" @selected(($filters['source_id'] ?? null) == $source->id)>{{ $source->name }} ({{ $source->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="import_id" class="form-label">{{ __('Fichier importé') }}</label>
                    <select id="import_id" name="import_id" class="form-select">
                        <option value="">{{ __('Tous les fichiers du périmètre') }}</option>
                        @foreach ($imports as $import)
                            <option value="{{ $import->id }}" @selected(($filters['import_id'] ?? null) == $import->id)>{{ $import->source?->code }} — {{ $import->original_filename }} — {{ $import->created_at?->format('d/m/Y') }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">{{ __('Les 250 imports terminés les plus récents sont proposés.') }}</div>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">{{ __('Date opération du') }}</label>
                    <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">{{ __('au') }}</label>
                    <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100" title="{{ __('Prévisualiser') }}"><i class="bi bi-search"></i></button>
                </div>
            </form>
        </div>
    </div>

    @if ($duplicatePreview)
        @php $filterFields = ['source_id', 'import_id', 'date_from', 'date_to']; @endphp
        <div class="alert alert-info" role="status">
            <strong>{{ __('Aperçu sans modification des données.') }}</strong>
            {{ __('Les nombres peuvent évoluer si des imports ou rapprochements sont exécutés avant le lancement.') }}
        </div>
        <div class="row g-3">
            <div class="col-lg-6">
                <section class="card h-100">
                    <div class="card-header fw-semibold">{{ __('Doublons candidats') }}</div>
                    <div class="card-body">
                        <p>{{ trans_choice(':count groupe candidat|:count groupes candidats', $duplicatePreview->groupsFound, ['count' => $duplicatePreview->groupsFound]) }}</p>
                        <p>{{ trans_choice(':count exception à créer|:count exceptions à créer', $duplicatePreview->exceptionsCreated, ['count' => $duplicatePreview->exceptionsCreated]) }}</p>
                        <p class="text-secondary mb-3">{{ __('Un doublon reste à confirmer manuellement ; aucune transaction ne sera supprimée.') }}</p>
                        <form action="{{ route('admin.matching-rules.detect-duplicates') }}" method="POST" onsubmit="return confirm('Lancer la détection sur le périmètre prévisualisé ?');">
                            @csrf
                            @foreach ($filterFields as $field)
                                @if (!empty($filters[$field]))
                                    <input type="hidden" name="{{ $field }}" value="{{ $filters[$field] }}">
                                @endif
                            @endforeach
                            <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-files me-1"></i>{{ __('Lancer la détection') }}</button>
                        </form>
                    </div>
                </section>
            </div>
            <div class="col-lg-6">
                <section class="card h-100">
                    <div class="card-header fw-semibold">{{ __('Non-rapprochés à signaler') }}</div>
                    <div class="card-body">
                        <p>{{ trans_choice(':count transaction non rapprochée|:count transactions non rapprochées', $unmatchedPreview, ['count' => $unmatchedPreview]) }}</p>
                        <p class="text-secondary mb-3">{{ __('Une ligne déjà signalée, en cours de revue ou qualifiée comme attendue ne sera pas recréée.') }}</p>
                        <form action="{{ route('admin.matching-rules.sweep-unmatched') }}" method="POST" onsubmit="return confirm('Créer les exceptions pour les transactions prévisualisées ?');">
                            @csrf
                            @foreach ($filterFields as $field)
                                @if (!empty($filters[$field]))
                                    <input type="hidden" name="{{ $field }}" value="{{ $filters[$field] }}">
                                @endif
                            @endforeach
                            <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-broom me-1"></i>{{ __('Créer les exceptions') }}</button>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    @endif
</x-app-layout>