<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Journal d\'audit') }}</h2>
    </x-slot>

    <div class="card">
        <div class="table-responsive">
            <table id="audit-logs-table" class="table table-hover mb-0 align-middle w-100">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Utilisateur') }}</th>
                        <th>{{ __('Événement') }}</th>
                        <th>{{ __('Sujet') }}</th>
                        <th>{{ __('IP') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                $('#audit-logs-table').DataTable({
                    processing: true,
                    serverSide: true,
                    order: [[0, 'desc']],
                    ajax: '{{ route('admin.audit-logs.data') }}',
                    columns: [
                        { data: 'date', name: 'created_at' },
                        { data: 'user', name: 'user', orderable: false, searchable: false },
                        { data: 'event', name: 'event' },
                        { data: 'subject', name: 'auditable_type', orderable: false, searchable: false },
                        { data: 'ip_address', name: 'ip_address' },
                        { data: 'actions', name: 'actions', orderable: false, searchable: false },
                    ],
                });
            });
        </script>
    @endpush
</x-app-layout>
