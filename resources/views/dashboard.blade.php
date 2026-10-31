{{--
    Tableau de bord : chiffres clés (DashboardMetricsService) et tableau de bord analytique filtrable.
    Les données analytiques sont relues en JSON (DashboardController::analytics) par resources/js/dashboard.js
    à chaque changement de filtre : indicateurs, graphiques, tableau croisé et opérations.
    Couleurs et styles : resources/css/_dashboard.scss (une même notion a la même teinte partout).
--}}
@php
    use App\Enums\ExceptionType;
    use App\Enums\MatchingResultStatus;

    $matchingKeys = array_map(fn ($case) => $case->value, MatchingResultStatus::cases());
    $matchingLabels = array_map(fn ($case) => $case->label(), MatchingResultStatus::cases());
    $matchingValues = array_map(fn ($case) => $matchingStats[$case->value] ?? 0, MatchingResultStatus::cases());

    $exceptionLabels = array_map(fn ($case) => $case->label(), ExceptionType::cases());
    $exceptionValues = array_map(fn ($case) => $exceptionStats['by_type'][$case->value] ?? 0, ExceptionType::cases());

    // Sans l'analyse détaillée : volume par source et évolution sur 30 jours (comme auparavant).
    $volumeLabels = array_map(fn ($row) => $row['source_code'], $volumeBySource);
    $volumeValues = array_map(fn ($row) => $row['count'], $volumeBySource);
    $trendLabels = array_map(fn ($row) => \Illuminate\Support\Carbon::parse($row['date'])->format('d/m'), $dailyTrend);
    $trendValues = array_map(fn ($row) => $row['count'], $dailyTrend);

    // Indicateurs : libellé, teinte (voir _dashboard.scss) et icône.
    $analysisKpis = [
        'lignes' => [__('Lignes importées'), 'violet', 'bi-database'],
        'montant_millimes' => [__('Montant (TND)'), 'blue', 'bi-cash-coin'],
        'rapprochees' => [__('Rapprochées'), 'emerald', 'bi-check2-circle'],
        'non_rapprochees' => [__('Non rapprochées'), 'amber', 'bi-hourglass-split'],
        'conflits' => [__('En conflit'), 'rose', 'bi-exclamation-octagon'],
        'taux_rapprochement' => [__('Taux de rapprochement'), 'fuchsia', 'bi-graph-up-arrow'],
        'taux_anomalie' => [__('Taux d’anomalie'), 'orange', 'bi-activity'],
    ];
    $operationKpis = [
        'total' => [__('Opérations'), 'violet', 'bi-gear'],
        'en_cours' => [__('En attente ou en cours'), 'amber', 'bi-arrow-repeat'],
        'terminees' => [__('Terminées'), 'emerald', 'bi-check2-circle'],
        'echecs' => [__('Échecs'), 'rose', 'bi-x-octagon'],
        'taux_succes' => [__('Taux de succès'), 'fuchsia', 'bi-trophy'],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-bold mb-0">{{ __('Tableau de bord') }}</h2>
    </x-slot>

    <div class="dash">
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-4">
                <div class="dash-hero tone-violet">
                    <div class="dash-hero-label">{{ __('Transactions totales') }}</div>
                    <div class="dash-hero-value">{{ number_format($totalTransactions, 0, ',', ' ') }}</div>
                    <i class="bi bi-arrow-left-right dash-hero-icon" aria-hidden="true"></i>
                </div>
            </div>
            @can('exceptions.viewAny')
                <div class="col-12 col-md-4">
                    <div class="dash-hero tone-rose">
                        <div class="dash-hero-label">{{ __('Exceptions ouvertes') }}</div>
                        <div class="dash-hero-value">{{ number_format($exceptionStats['open'], 0, ',', ' ') }}</div>
                        <i class="bi bi-shield-exclamation dash-hero-icon" aria-hidden="true"></i>
                    </div>
                </div>
            @endcan
            @can('imports.viewAny')
                <div class="col-12 col-md-4">
                    <div class="dash-hero tone-cyan">
                        <div class="dash-hero-label">{{ __('Imports ce mois-ci') }}</div>
                        <div class="dash-hero-value">{{ number_format($importStats['this_month'], 0, ',', ' ') }}</div>
                        <i class="bi bi-cloud-arrow-up dash-hero-icon" aria-hidden="true"></i>
                    </div>
                </div>
            @endcan
        </div>

        {{-- ===================== Tableau de bord analytique ===================== --}}
        <div id="dashboard-analytics" data-url="{{ route('dashboard.analytics') }}" data-can-analyse="{{ $canAnalyse ? '1' : '0' }}">
            <form id="analytics-filters" class="card dash-card mb-3" onsubmit="return false">
                <div class="card-header">
                    <span class="dash-chip tone-violet"><i class="bi bi-funnel"></i></span>
                    <span>{{ __('Filtres') }}</span>
                    <span class="dash-status" data-analytics-status>{{ __('Chargement…') }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-md-2">
                            <label for="f-annee" class="form-label">{{ __('Année') }}</label>
                            <select id="f-annee" name="annee" class="form-select form-select-sm">
                                <option value="">{{ __('Toutes') }}</option>
                                @foreach ($analyticsOptions['annees'] ?? [] as $year)
                                    <option value="{{ $year }}">{{ $year }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label for="f-debut" class="form-label">{{ __('Du') }}</label>
                            <input id="f-debut" type="date" name="date_debut" class="form-control form-control-sm">
                        </div>
                        <div class="col-6 col-md-2">
                            <label for="f-fin" class="form-label">{{ __('Au') }}</label>
                            <input id="f-fin" type="date" name="date_fin" class="form-control form-control-sm">
                        </div>
                        @if ($canAnalyse)
                            <div class="col-12 col-md-3">
                                <span class="form-label d-block">{{ __('Sources') }}</span>
                                @foreach ($analyticsOptions['sources'] as $code)
                                    <label class="filter-chip" for="f-source-{{ $code }}">
                                        <input class="form-check-input" type="checkbox" name="sources[]" value="{{ $code }}" id="f-source-{{ $code }}">
                                        {{ $code }}
                                    </label>
                                @endforeach
                            </div>
                            <div class="col-12 col-md-3">
                                <span class="form-label d-block">{{ __('Statut de rapprochement') }}</span>
                                @foreach ($analyticsOptions['statuts'] as $status)
                                    <label class="filter-chip" for="f-statut-{{ $status['valeur'] }}">
                                        <input class="form-check-input" type="checkbox" name="statuts[]" value="{{ $status['valeur'] }}" id="f-statut-{{ $status['valeur'] }}">
                                        {{ $status['libelle'] }}
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        <div class="col-12 col-md-6">
                            <span class="form-label d-block">{{ __('Type d’opération') }}</span>
                            @foreach ($operationTypes as $type => $label)
                                <label class="filter-chip" for="f-type-{{ $type }}">
                                    <input class="form-check-input" type="checkbox" name="types[]" value="{{ $type }}" id="f-type-{{ $type }}">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @if ($canViewAllOperations)
                            <div class="col-6 col-md-3">
                                <label for="f-utilisateur" class="form-label">{{ __('Utilisateur (opérations)') }}</label>
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
                <h3 class="dash-section-title">{{ __('Analyse du rapprochement') }}</h3>
                <div class="row g-3 mb-3" data-kpis>
                    @foreach ($analysisKpis as $key => [$label, $tone, $icon])
                        <div class="col-6 col-md-4 col-xl">
                            <div class="dash-kpi tone-{{ $tone }}">
                                <span class="dash-chip dash-chip-lg"><i class="bi {{ $icon }}"></i></span>
                                <div class="dash-kpi-body">
                                    <div class="dash-kpi-label">{{ $label }}</div>
                                    <div class="dash-kpi-value" data-kpi="{{ $key }}">—</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-lg-8">
                        <div class="card dash-card h-100">
                            <div class="card-header">
                                <span class="dash-chip tone-violet"><i class="bi bi-graph-up"></i></span>
                                <span>{{ __('Évolution par statut') }} <span class="dash-card-subtitle" data-timeline-granularity></span></span>
                            </div>
                            <div class="card-body dash-chart"><canvas id="chart-analytics-timeline" height="130"></canvas></div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="card dash-card h-100">
                            <div class="card-header">
                                <span class="dash-chip tone-fuchsia"><i class="bi bi-pie-chart"></i></span>
                                <span>{{ __('Répartition par statut') }}</span>
                            </div>
                            <div class="card-body dash-chart"><canvas id="chart-analytics-status" height="200"></canvas></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card dash-card h-100">
                            <div class="card-header">
                                <span class="dash-chip tone-blue"><i class="bi bi-bar-chart-steps"></i></span>
                                <span>{{ __('Lignes par source et par statut') }}</span>
                            </div>
                            <div class="card-body dash-chart"><canvas id="chart-analytics-source" height="90"></canvas></div>
                        </div>
                    </div>
                </div>

                <div class="card dash-card mb-3">
                    <div class="card-header">
                        <span class="dash-chip tone-emerald"><i class="bi bi-grid-3x3-gap"></i></span>
                        <span>{{ __('Tableau croisé dynamique') }}</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-2 align-items-end mb-3">
                            <div class="col-6 col-md-3">
                                <label for="p-lignes" class="form-label">{{ __('Lignes') }}</label>
                                <select id="p-lignes" name="dim_lignes" form="analytics-filters" class="form-select form-select-sm">
                                    @foreach ($analyticsOptions['dimensions'] as $key => $label)
                                        <option value="{{ $key }}" @selected($key === 'source')>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <label for="p-colonnes" class="form-label">{{ __('Colonnes') }}</label>
                                <select id="p-colonnes" name="dim_colonnes" form="analytics-filters" class="form-select form-select-sm">
                                    <option value="aucune">{{ __('— Aucune —') }}</option>
                                    @foreach ($analyticsOptions['dimensions'] as $key => $label)
                                        <option value="{{ $key }}" @selected($key === 'statut')>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <label for="p-mesure" class="form-label">{{ __('Mesure') }}</label>
                                <select id="p-mesure" name="mesure" form="analytics-filters" class="form-select form-select-sm">
                                    @foreach ($analyticsOptions['mesures'] as $key => $label)
                                        <option value="{{ $key }}">{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <span class="form-label d-block">{{ __('Afficher') }}</span>
                                <div class="btn-group btn-group-sm dash-segmented" role="group" aria-label="{{ __('Valeurs ou pourcentages') }}" data-pivot-mode>
                                    <button type="button" class="btn active" data-mode="valeur">{{ __('Valeurs') }}</button>
                                    <button type="button" class="btn" data-mode="pct_total">{{ __('% du total') }}</button>
                                    <button type="button" class="btn" data-mode="pct_ligne">{{ __('% de la ligne') }}</button>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle dash-table dash-pivot" data-pivot-table></table>
                        </div>
                        <p class="small text-secondary mt-2 mb-0" data-pivot-note></p>
                    </div>
                </div>
            @endif

            <h3 class="dash-section-title">{{ $canViewAllOperations ? __('Opérations') : __('Mes opérations') }}</h3>
            <div class="row g-3 mb-3" data-operations-kpis>
                @foreach ($operationKpis as $key => [$label, $tone, $icon])
                    <div class="col-6 col-md">
                        <div class="dash-kpi tone-{{ $tone }}">
                            <span class="dash-chip dash-chip-lg"><i class="bi {{ $icon }}"></i></span>
                            <div class="dash-kpi-body">
                                <div class="dash-kpi-label">{{ $label }}</div>
                                <div class="dash-kpi-value" data-operation-kpi="{{ $key }}">—</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="row g-3 mb-3">
                <div class="col-12 col-lg-7">
                    <div class="card dash-card h-100">
                        <div class="card-header">
                            <span class="dash-chip tone-violet"><i class="bi bi-list-ul"></i></span>
                            <span>{{ __('Par type d’opération') }}</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm dash-table" data-operations-types>
                                <thead><tr><th>{{ __('Type') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Terminées') }}</th><th class="text-end">{{ __('Échecs') }}</th><th class="text-end">{{ __('Durée moyenne') }}</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-5">
                    <div class="card dash-card h-100">
                        <div class="card-header">
                            <span class="dash-chip tone-rose"><i class="bi bi-bug"></i></span>
                            <span>{{ __('Derniers échecs') }}</span>
                        </div>
                        <ul class="list-group list-group-flush small dash-failures" data-operations-failures></ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== Vue d'ensemble (indicateurs existants) ===================== --}}
        <div class="row g-3 mb-3">
            @can('matching-results.viewAny')
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card dash-card h-100">
                        <div class="card-header">
                            <span class="dash-chip tone-emerald"><i class="bi bi-diagram-3"></i></span>
                            <span>{{ __('Résultats de rapprochement') }}</span>
                        </div>
                        <div class="card-body dash-chart">
                            <canvas id="chart-matching" height="200"></canvas>
                        </div>
                    </div>
                </div>
            @endcan
            @can('exceptions.viewAny')
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card dash-card h-100">
                        <div class="card-header">
                            <span class="dash-chip tone-rose"><i class="bi bi-exclamation-triangle"></i></span>
                            <span>{{ __('Exceptions par type') }}</span>
                        </div>
                        <div class="card-body dash-chart">
                            <canvas id="chart-exceptions" height="200"></canvas>
                        </div>
                    </div>
                </div>
            @endcan
            @unless ($canAnalyse)
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card dash-card h-100">
                        <div class="card-header">
                            <span class="dash-chip tone-blue"><i class="bi bi-bar-chart"></i></span>
                            <span>{{ __('Volume par source') }}</span>
                        </div>
                        <div class="card-body dash-chart">
                            <canvas id="chart-volume" height="200"></canvas>
                        </div>
                    </div>
                </div>
            @endunless
        </div>

        @unless ($canAnalyse)
            <div class="card dash-card">
                <div class="card-header">
                    <span class="dash-chip tone-violet"><i class="bi bi-graph-up"></i></span>
                    <span>{{ __('Volume de transactions (30 derniers jours)') }}</span>
                </div>
                <div class="card-body dash-chart">
                    <canvas id="chart-trend" height="120"></canvas>
                </div>
            </div>
        @endunless
    </div>

    @push('scripts')
        <script>
            // Couleurs : window.dashboardTheme (resources/js/dashboard.js), les mêmes que le reste de la page.
            document.addEventListener('DOMContentLoaded', function () {
                const theme = window.dashboardTheme;

                @can('matching-results.viewAny')
                    new Chart(document.getElementById('chart-matching'), {
                        type: 'doughnut',
                        data: {
                            labels: @json($matchingLabels),
                            datasets: [{ data: @json($matchingValues), backgroundColor: @json($matchingKeys).map(theme.statusColor) }],
                        },
                    });
                @endcan

                @can('exceptions.viewAny')
                    new Chart(document.getElementById('chart-exceptions'), {
                        type: 'doughnut',
                        data: {
                            labels: @json($exceptionLabels),
                            datasets: [{ data: @json($exceptionValues), backgroundColor: @json($exceptionLabels).map((label, i) => theme.seriesColor(i)) }],
                        },
                    });
                @endcan

                @unless ($canAnalyse)
                    new Chart(document.getElementById('chart-volume'), {
                        type: 'bar',
                        data: {
                            labels: @json($volumeLabels),
                            datasets: [{ label: '{{ __('Transactions') }}', data: @json($volumeValues), backgroundColor: theme.gradient('blue') }],
                        },
                        options: { scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } },
                    });

                    new Chart(document.getElementById('chart-trend'), {
                        type: 'line',
                        data: {
                            labels: @json($trendLabels),
                            datasets: [{ label: '{{ __('Transactions par jour') }}', data: @json($trendValues), borderColor: theme.color('violet'), backgroundColor: theme.area('violet'), tension: 0.35, fill: true }],
                        },
                        options: { scales: { y: { beginAtZero: true } } },
                    });
                @endunless
            });
        </script>
    @endpush
</x-app-layout>
