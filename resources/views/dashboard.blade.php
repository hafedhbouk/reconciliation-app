@php
    use App\Enums\ExceptionType;
    use App\Enums\MatchingResultStatus;

    $matchingTotal = array_sum($matchingStats);
    $matchedCount = $matchingStats[MatchingResultStatus::Matched->value] ?? 0;
    $matchRate = $matchingTotal > 0 ? round($matchedCount / $matchingTotal * 100, 1) : 0;

    $matchingLabels = array_map(fn ($case) => $case->label(), MatchingResultStatus::cases());
    $matchingValues = array_map(fn ($case) => $matchingStats[$case->value] ?? 0, MatchingResultStatus::cases());

    $exceptionLabels = array_map(fn ($case) => $case->label(), ExceptionType::cases());
    $exceptionValues = array_map(fn ($case) => $exceptionStats['by_type'][$case->value] ?? 0, ExceptionType::cases());

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
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-secondary small text-uppercase">{{ __('Transactions totales') }}</div>
                    <div class="fs-3 fw-semibold">{{ number_format($totalTransactions) }}</div>
                </div>
            </div>
        </div>
        @can('exceptions.viewAny')
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-secondary small text-uppercase">{{ __('Exceptions ouvertes') }}</div>
                        <div class="fs-3 fw-semibold text-danger">{{ number_format($exceptionStats['open']) }}</div>
                    </div>
                </div>
            </div>
        @endcan
        @can('imports.viewAny')
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-secondary small text-uppercase">{{ __('Imports ce mois-ci') }}</div>
                        <div class="fs-3 fw-semibold">{{ number_format($importStats['this_month']) }}</div>
                    </div>
                </div>
            </div>
        @endcan
        @can('matching-results.viewAny')
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-secondary small text-uppercase">{{ __('Taux de rapprochement') }}</div>
                        <div class="fs-3 fw-semibold text-success">{{ $matchRate }}%</div>
                    </div>
                </div>
            </div>
        @endcan
    </div>

    <div class="row g-3 mb-3">
        @can('matching-results.viewAny')
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header fw-semibold">{{ __('Résultats de rapprochement') }}</div>
                    <div class="card-body">
                        <canvas id="chart-matching" height="220"></canvas>
                    </div>
                </div>
            </div>
        @endcan
        @can('exceptions.viewAny')
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header fw-semibold">{{ __('Exceptions par type') }}</div>
                    <div class="card-body">
                        <canvas id="chart-exceptions" height="220"></canvas>
                    </div>
                </div>
            </div>
        @endcan
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header fw-semibold">{{ __('Volume par source') }}</div>
                <div class="card-body">
                    <canvas id="chart-volume" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-semibold">{{ __('Volume de transactions (30 derniers jours)') }}</div>
        <div class="card-body">
            <canvas id="chart-trend" height="90"></canvas>
        </div>
    </div>

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
            });
        </script>
    @endpush
</x-app-layout>
