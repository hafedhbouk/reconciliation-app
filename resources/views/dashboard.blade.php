{{--
    Tableau de bord : chiffres clés (DashboardMetricsService) et tableau de bord analytique filtrable.
    Les données analytiques sont relues en JSON (DashboardController::analytics) par resources/js/dashboard.js
    à chaque changement de filtre : indicateurs, graphiques, tableau croisé et opérations.
--}}
@php
    use App\Enums\ExceptionType;
    use App\Enums\MatchingResultStatus;

    $matchingLabels = array_map(fn ($case) => $case->label(), MatchingResultStatus::cases());
    $matchingValues = array_map(fn ($case) => $matchingStats[$case->value] ?? 0, MatchingResultStatus::cases());

    $exceptionLabels = array_map(fn ($case) => $case->label(), ExceptionType::cases());
    $exceptionValues = array_map(fn ($case) => $exceptionStats['by_type'][$case->value] ?? 0, ExceptionType::cases());

    // Sans l'analyse détaillée : volume par source et évolution sur 30 jours (comme auparavant).
    $volumeLabels = array_map(fn ($row) => $row['source_code'], $volumeBySource);
    $volumeValues = array_map(fn ($row) => $row['count'], $volumeBySource);
    $trendLabels = array_map(fn ($row) => \Illuminate\Support\Carbon::parse($row['date'])->format('d/m'), $dailyTrend);
    $trendValues = array_map(fn ($row) => $row['count'], $dailyTrend);
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Tableau de bord') }}</h2>
    </x-slot>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-secondary small text-uppercase">{{ __('Transactions totales') }}</div>
                    <div class="fs-3 fw-semibold">{{ number_format($totalTransactions, 0, ',', ' ') }}</div>
                </div>
            </div>
        </div>
        @can('exceptions.viewAny')
            <div class="col-6 col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-secondary small text-uppercase">{{ __('Exceptions ouvertes') }}</div>
                        <div class="fs-3 fw-semibold text-danger">{{ number_format($exceptionStats['open'], 0, ',', ' ') }}</div>
                    </div>
                </div>
            </div>
        @endcan
        @can('imports.viewAny')
            <div class="col-6 col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-secondary small text-uppercase">{{ __('Imports ce mois-ci') }}</div>
                        <div class="fs-3 fw-semibold">{{ number_format($importStats['this_month'], 0, ',', ' ') }}</div>
                    </div>
                </div>
            </div>
        @endcan
    </div>

    {{-- ===================== Tableau de bord analytique ===================== --}}
    <div id="dashboard-analytics" data-url="{{ route('dashboard.analytics') }}" data-can-analyse="{{ $canAnalyse ? '1' : '0' }}">
        <form id="analytics-filters" class="card mb-3" onsubmit="return false">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="fw-semibold"><i class="bi bi-funnel me-1"></i>{{ __('Filtres') }}</span>
                <span class="small text-secondary" data-analytics-status>{{ __('Chargement…') }}</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-2">
                        <label for="f-annee" class="form-label small fw-semibold">{{ __('Année') }}</label>
                        <select id="f-annee" name="annee" class="form-select form-select-sm">
                            <option value="">{{ __('Toutes') }}</option>
                            @foreach ($analyticsOptions['annees'] ?? [] as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="f-debut" class="form-label small fw-semibold">{{ __('Du') }}</label>
                        <input id="f-debut" type="date" name="date_debut" class="form-control form-control-sm">
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="f-fin" class="form-label small fw-semibold">{{ __('Au') }}</label>
                        <input id="f-fin" type="date" name="date_fin" class="form-control form-control-sm">
                    </div>
                    @if ($canAnalyse)
                        <div class="col-12 col-md-3">
                            <span class="form-label small fw-semibold d-block">{{ __('Sources') }}</span>
                            @foreach ($analyticsOptions['sources'] as $code)
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="sources[]" value="{{ $code }}" id="f-source-{{ $code }}">
                                    <label class="form-check-label small" for="f-source-{{ $code }}">{{ $code }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="col-12 col-md-3">
                            <span class="form-label small fw-semibold d-block">{{ __('Statut de rapprochement') }}</span>
                            @foreach ($analyticsOptions['statuts'] as $status)
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="statuts[]" value="{{ $status['valeur'] }}" id="f-statut-{{ $status['valeur'] }}">
                                    <label class="form-check-label small" for="f-statut-{{ $status['valeur'] }}">{{ $status['libelle'] }}</label>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div class="col-12 col-md-6">
                        <span class="form-label small fw-semibold d-block">{{ __('Type d’opération') }}</span>
                        @foreach ($operationTypes as $type => $label)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="types[]" value="{{ $type }}" id="f-type-{{ $type }}">
                                <label class="form-check-label small" for="f-type-{{ $type }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                    @if ($canViewAllOperations)
                        <div class="col-6 col-md-3">
                            <label for="f-utilisateur" class="form-label small fw-semibold">{{ __('Utilisateur (opérations)') }}</label>
                            <select id="f-utilisateur" name="utilisateur" class="form-select form-select-sm">
                                <option value="">{{ __('Tous') }}</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-12 col-md-3 d-flex align-items-end gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-analytics-reset>
                            <i class="bi bi-x-circle me-1"></i>{{ __('Réinitialiser') }}
                        </button>
                        @if ($canAnalyse)
                            <button type="button" class="btn btn-sm btn-outline-primary" data-analytics-refresh title="{{ __('Recalculer à partir des données actuelles') }}">
                                <i class="bi bi-arrow-clockwise me-1"></i>{{ __('Actualiser') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </form>

        @if ($canAnalyse)
            <h3 class="fs-5 fw-semibold mb-2">{{ __('Analyse du rapprochement') }}</h3>
            <div class="row g-3 mb-3" data-kpis>
                @foreach ([
                    'lignes' => __('Lignes importées'),
                    'montant_millimes' => __('Montant (TND)'),
                    'rapprochees' => __('Rapprochées'),
                    'non_rapprochees' => __('Non rapprochées'),
                    'conflits' => __('En conflit'),
                    'taux_rapprochement' => __('Taux de rapprochement'),
                    'taux_anomalie' => __('Taux d’anomalie'),
                ] as $key => $label)
                    <div class="col-6 col-md-4 col-xl">
                        <div class="card h-100">
                            <div class="card-body py-2">
                                <div class="text-secondary small text-uppercase">{{ $label }}</div>
                                <div class="fs-5 fw-semibold" data-kpi="{{ $key }}">—</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-lg-8">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">{{ __('Évolution par statut') }} <span class="small text-secondary" data-timeline-granularity></span></div>
                        <div class="card-body"><canvas id="chart-analytics-timeline" height="130"></canvas></div>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">{{ __('Répartition par statut') }}</div>
                        <div class="card-body"><canvas id="chart-analytics-status" height="200"></canvas></div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">{{ __('Lignes par source et par statut') }}</div>
                        <div class="card-body"><canvas id="chart-analytics-source" height="90"></canvas></div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header fw-semibold">{{ __('Tableau croisé dynamique') }}</div>
                <div class="card-body">
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-6 col-md-3">
                            <label for="p-lignes" class="form-label small fw-semibold">{{ __('Lignes') }}</label>
                            <select id="p-lignes" name="dim_lignes" form="analytics-filters" class="form-select form-select-sm">
                                @foreach ($analyticsOptions['dimensions'] as $key => $label)
                                    <option value="{{ $key }}" @selected($key === 'source')>{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label for="p-colonnes" class="form-label small fw-semibold">{{ __('Colonnes') }}</label>
                            <select id="p-colonnes" name="dim_colonnes" form="analytics-filters" class="form-select form-select-sm">
                                <option value="aucune">{{ __('— Aucune —') }}</option>
                                @foreach ($analyticsOptions['dimensions'] as $key => $label)
                                    <option value="{{ $key }}" @selected($key === 'statut')>{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label for="p-mesure" class="form-label small fw-semibold">{{ __('Mesure') }}</label>
                            <select id="p-mesure" name="mesure" form="analytics-filters" class="form-select form-select-sm">
                                @foreach ($analyticsOptions['mesures'] as $key => $label)
                                    <option value="{{ $key }}">{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="form-label small fw-semibold d-block">{{ __('Afficher') }}</span>
                            <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Valeurs ou pourcentages') }}" data-pivot-mode>
                                <button type="button" class="btn btn-outline-secondary active" data-mode="valeur">{{ __('Valeurs') }}</button>
                                <button type="button" class="btn btn-outline-secondary" data-mode="pct_total">{{ __('% du total') }}</button>
                                <button type="button" class="btn btn-outline-secondary" data-mode="pct_ligne">{{ __('% de la ligne') }}</button>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" data-pivot-table></table>
                    </div>
                    <p class="small text-secondary mt-2 mb-0" data-pivot-note></p>
                </div>
            </div>
        @endif

        <h3 class="fs-5 fw-semibold mb-2">{{ $canViewAllOperations ? __('Opérations') : __('Mes opérations') }}</h3>
        <div class="row g-3 mb-3" data-operations-kpis>
            @foreach (['total' => __('Opérations'), 'en_cours' => __('En attente ou en cours'), 'terminees' => __('Terminées'), 'echecs' => __('Échecs'), 'taux_succes' => __('Taux de succès')] as $key => $label)
                <div class="col-6 col-md">
                    <div class="card h-100">
                        <div class="card-body py-2">
                            <div class="text-secondary small text-uppercase">{{ $label }}</div>
                            <div class="fs-5 fw-semibold" data-operation-kpi="{{ $key }}">—</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="row g-3 mb-3">
            <div class="col-12 col-lg-7">
                <div class="card h-100">
                    <div class="card-header fw-semibold">{{ __('Par type d’opération') }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" data-operations-types>
                            <thead><tr><th>{{ __('Type') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Terminées') }}</th><th class="text-end">{{ __('Échecs') }}</th><th class="text-end">{{ __('Durée moyenne') }}</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="card h-100">
                    <div class="card-header fw-semibold">{{ __('Derniers échecs') }}</div>
                    <ul class="list-group list-group-flush small" data-operations-failures></ul>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Vue d'ensemble (indicateurs existants) ===================== --}}
    <div class="row g-3 mb-3">
        @can('matching-results.viewAny')
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-header fw-semibold">{{ __('Résultats de rapprochement') }}</div>
                    <div class="card-body">
                        <canvas id="chart-matching" height="200"></canvas>
                    </div>
                </div>
            </div>
        @endcan
        @can('exceptions.viewAny')
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-header fw-semibold">{{ __('Exceptions par type') }}</div>
                    <div class="card-body">
                        <canvas id="chart-exceptions" height="200"></canvas>
                    </div>
                </div>
            </div>
        @endcan
        @unless ($canAnalyse)
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-header fw-semibold">{{ __('Volume par source') }}</div>
                    <div class="card-body">
                        <canvas id="chart-volume" height="200"></canvas>
                    </div>
                </div>
            </div>
        @endunless
    </div>

    @unless ($canAnalyse)
        <div class="card">
            <div class="card-header fw-semibold">{{ __('Volume de transactions (30 derniers jours)') }}</div>
            <div class="card-body">
                <canvas id="chart-trend" height="120"></canvas>
            </div>
        </div>
    @endunless

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const palette = ['#0d6efd', '#198754', '#dc3545', '#ffc107', '#6f42c1', '#20c997', '#fd7e14'];

                @can('matching-results.viewAny')
                    new Chart(document.getElementById('chart-matching'), {
                        type: 'doughnut',
                        data: {
                            labels: @json($matchingLabels),
                            datasets: [{ data: @json($matchingValues), backgroundColor: palette }],
                        },
                    });
                @endcan

                @can('exceptions.viewAny')
                    new Chart(document.getElementById('chart-exceptions'), {
                        type: 'doughnut',
                        data: {
                            labels: @json($exceptionLabels),
                            datasets: [{ data: @json($exceptionValues), backgroundColor: palette }],
                        },
                    });
                @endcan

                @unless ($canAnalyse)
                    new Chart(document.getElementById('chart-volume'), {
                        type: 'bar',
                        data: {
                            labels: @json($volumeLabels),
                            datasets: [{ label: '{{ __('Transactions') }}', data: @json($volumeValues), backgroundColor: '#0d6efd' }],
                        },
                        options: { scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } },
                    });

                    new Chart(document.getElementById('chart-trend'), {
                        type: 'line',
                        data: {
                            labels: @json($trendLabels),
                            datasets: [{ label: '{{ __('Transactions par jour') }}', data: @json($trendValues), borderColor: '#0d6efd', tension: 0.3, fill: false }],
                        },
                        options: { scales: { y: { beginAtZero: true } } },
                    });
                @endunless
            });
        </script>
    @endpush
</x-app-layout>
