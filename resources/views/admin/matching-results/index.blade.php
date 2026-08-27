<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fs-4 fw-semibold mb-0">{{ __('Résultats de rapprochement') }}</h2>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.matching-results.export', 'csv') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
                <a href="{{ route('admin.matching-results.export', 'xlsx') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
                <a href="{{ route('admin.matching-results.export', 'pdf') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
            </div>
        </div>
    </x-slot>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.matching-results.index') }}" class="row g-3 align-items-end">
                <div class="col-auto">
                    <label for="rule-filter" class="form-label small fw-semibold">{{ __('Règle') }}</label>
                    <select name="matching_rule_id" id="rule-filter" class="form-select form-select-sm">
                        <option value="">{{ __('Toutes les règles') }}</option>
                        @foreach ($rules as $rule)
                            <option value="{{ $rule->id }}" {{ request('matching_rule_id') == $rule->id ? 'selected' : '' }}>
                                {{ $rule->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label for="batch-filter" class="form-label small fw-semibold">{{ __('Lot') }}</label>
                    <select name="batch_reference" id="batch-filter" class="form-select form-select-sm">
                        <option value="">{{ __('Tous les lots') }}</option>
                        @foreach ($batches as $batch)
                            <option value="{{ $batch->batch_reference }}" {{ request('batch_reference') === $batch->batch_reference ? 'selected' : '' }}>
                                {{ $batch->matched_at?->format('d/m/Y H:i') }} — {{ $batch->batch_reference }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label for="matched_at_from" class="form-label small fw-semibold">{{ __('Date de début') }}</label>
                    <input type="date" name="matched_at_from" id="matched_at_from" class="form-control form-control-sm" value="{{ request('matched_at_from') }}">
                </div>
                <div class="col-auto">
                    <label for="matched_at_to" class="form-label small fw-semibold">{{ __('Date de fin') }}</label>
                    <input type="date" name="matched_at_to" id="matched_at_to" class="form-control form-control-sm" value="{{ request('matched_at_to') }}">
                </div>
                <div class="col-auto">
                    <label for="status-filter" class="form-label small fw-semibold">{{ __('Statut') }}</label>
                    <select name="status" id="status-filter" class="form-select form-select-sm">
                        <option value="">{{ __('Tous les statuts') }}</option>
                        <option value="matched" {{ request('status') === 'matched' ? 'selected' : '' }}>{{ __('Rapproché') }}</option>
                        <option value="partial" {{ request('status') === 'partial' ? 'selected' : '' }}>{{ __('Partiel') }}</option>
                        <option value="conflict" {{ request('status') === 'conflict' ? 'selected' : '' }}>{{ __('Conflit') }}</option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>{{ __('Rejeté') }}</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-dark">{{ __('Filtrer') }}</button>
                    @if (request()->hasAny(['matching_rule_id', 'batch_reference', 'matched_at_from', 'matched_at_to', 'status']))
                        <a href="{{ route('admin.matching-results.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Réinitialiser') }}</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table id="matching-results-table" class="table table-hover mb-0 align-middle w-100">
                <thead>
                    <tr>
                        <th>{{ __('Règle') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th>{{ __('Confiance') }}</th>
                        <th>{{ __('Traité par') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Lot') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                $('#matching-results-table').DataTable({
                    processing: true,
                    serverSide: true,
                    order: [[4, 'desc']],
                    ajax: {
                        url: '{{ route('admin.matching-results.data') }}',
                        data: function (d) {
                            d.matching_rule_id = $('#rule-filter').val();
                            d.batch_reference = $('#batch-filter').val();
                            d.matched_at_from = $('#matched_at_from').val();
                            d.matched_at_to = $('#matched_at_to').val();
                            d.status = $('#status-filter').val();
                        }
                    },
                    columns: [
                        { data: 'rule_name', name: 'matchingRule.name', orderable: false },
                        { data: 'status', name: 'status' },
                        { data: 'confidence_score', name: 'confidence_score' },
                        { data: 'matched_by', name: 'matchedByUser.name', orderable: false },
                        { data: 'matched_at', name: 'matched_at' },
                        { data: 'batch_reference', name: 'batch_reference' },
                        { data: 'actions', name: 'actions', orderable: false, searchable: false },
                    ],
                });
            });
        </script>
    @endpush
</x-app-layout>
