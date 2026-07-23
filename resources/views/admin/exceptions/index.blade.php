<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fs-4 fw-semibold mb-0">{{ __('Exceptions') }}</h2>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.exceptions.export', 'csv') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
                <a href="{{ route('admin.exceptions.export', 'xlsx') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
                <a href="{{ route('admin.exceptions.export', 'pdf') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
            </div>
        </div>
    </x-slot>

    <div class="card">
        <div class="table-responsive">
            <table id="exceptions-table" class="table table-hover mb-0 align-middle w-100">
                <thead>
                    <tr>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th>{{ __('Source / Référence') }}</th>
                        <th>{{ __('Assigné à') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                $('#exceptions-table').DataTable({
                    processing: true,
                    serverSide: true,
                    order: [[4, 'desc']],
                    ajax: '{{ route('admin.exceptions.data') }}',
                    columns: [
                        { data: 'type_label', name: 'type' },
                        { data: 'status', name: 'status' },
                        { data: 'source_reference', name: 'source_reference', orderable: false, searchable: false },
                        { data: 'assigned_to', name: 'assignedTo.name', orderable: false },
                        { data: 'created_at', name: 'created_at' },
                        { data: 'actions', name: 'actions', orderable: false, searchable: false },
                    ],
                });
            });
        </script>
    @endpush
</x-app-layout>
