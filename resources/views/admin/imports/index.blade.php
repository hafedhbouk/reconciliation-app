<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fs-4 fw-semibold mb-0">{{ __('Imports') }}</h2>
            @can('imports.create')
                <a href="{{ route('admin.imports.create') }}" class="btn btn-dark btn-sm">
                    <i class="bi bi-upload me-1"></i>{{ __('Nouvel import') }}
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="card">
        <div class="table-responsive">
            <table id="imports-table" class="table table-hover mb-0 align-middle w-100">
                <thead>
                    <tr>
                        <th>{{ __('Source') }}</th>
                        <th>{{ __('Fichier') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th>{{ __('Lignes') }}</th>
                        <th>{{ __('Erreurs') }}</th>
                        <th>{{ __('Durée') }}</th>
                        <th>{{ __('Importé par') }}</th>
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
                $('#imports-table').DataTable({
                    processing: true,
                    serverSide: true,
                    order: [[7, 'desc']],
                    ajax: '{{ route('admin.imports.data') }}',
                    columns: [
                        { data: 'source', name: 'source_id' },
                        { data: 'original_filename', name: 'original_filename' },
                        { data: 'status', name: 'status' },
                        { data: 'success_rows', name: 'success_rows' },
                        { data: 'error_rows', name: 'error_rows' },
                        { data: 'duration', name: 'duration', orderable: false, searchable: false },
                        { data: 'uploaded_by', name: 'uploaded_by', orderable: false, searchable: false },
                        { data: 'created_at', name: 'created_at' },
                        { data: 'actions', name: 'actions', orderable: false, searchable: false },
                    ],
                });
            });
        </script>
    @endpush
</x-app-layout>
