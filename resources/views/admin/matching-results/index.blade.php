<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Résultats de rapprochement') }}</h2>
    </x-slot>

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
                    ajax: '{{ route('admin.matching-results.data') }}',
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
