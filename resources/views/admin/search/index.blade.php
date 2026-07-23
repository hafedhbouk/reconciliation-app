<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Recherche multi-critères') }}</h2>
    </x-slot>

    <div class="card mb-3">
        <div class="card-body">
            <form id="search-filter-form" class="row g-2">
                <div class="col-md-2">
                    <select name="source_id" class="form-select form-select-sm">
                        <option value="">{{ __('Toutes les sources') }}</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->id }}">{{ $source->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="text" name="reference" class="form-control form-control-sm" placeholder="{{ __('Référence') }}">
                </div>
                <div class="col-md-2">
                    <select name="matching_status" class="form-select form-select-sm">
                        <option value="">{{ __('Tous les statuts') }}</option>
                        @foreach (\App\Enums\MatchingStatus::cases() as $case)
                            <option value="{{ $case->value }}">{{ $case->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="text" name="canal" class="form-control form-control-sm" placeholder="{{ __('Canal') }}">
                </div>
                <div class="col-md-2">
                    <input type="number" name="amount_min" class="form-control form-control-sm" placeholder="{{ __('Montant min') }}">
                </div>
                <div class="col-md-2">
                    <input type="number" name="amount_max" class="form-control form-control-sm" placeholder="{{ __('Montant max') }}">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 d-flex align-items-center">
                    <button type="button" id="search-submit" class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-search me-1"></i>{{ __('Rechercher') }}
                    </button>
                </div>
            </form>
        </div>
        <div class="card-footer d-flex gap-2">
            <a href="#" id="export-csv" class="btn btn-sm btn-outline-secondary"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
            <a href="#" id="export-xlsx" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
            <a href="#" id="export-pdf" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table id="search-table" class="table table-hover mb-0 align-middle w-100">
                <thead>
                    <tr>
                        <th>{{ __('Source') }}</th>
                        <th>{{ __('Référence') }}</th>
                        <th>{{ __('Montant') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Canal') }}</th>
                        <th>{{ __('Statut') }}</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('search-filter-form');
                const baseDataUrl = '{{ route('admin.search.data') }}';
                const exportUrls = {
                    csv: '{{ route('admin.search.export', 'csv') }}',
                    xlsx: '{{ route('admin.search.export', 'xlsx') }}',
                    pdf: '{{ route('admin.search.export', 'pdf') }}',
                };

                function currentParams() {
                    return new URLSearchParams(new FormData(form)).toString();
                }

                const table = $('#search-table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: { url: baseDataUrl, data: (d) => Object.assign(d, Object.fromEntries(new FormData(form))) },
                    columns: [
                        { data: 'source', name: 'source', orderable: false, searchable: false },
                        { data: 'normalized_reference', name: 'normalized_reference' },
                        { data: 'normalized_amount_millimes', name: 'normalized_amount_millimes' },
                        { data: 'normalized_date', name: 'normalized_date' },
                        { data: 'canal', name: 'canal', orderable: false, searchable: false },
                        { data: 'matching_status', name: 'matching_status' },
                    ],
                });

                document.getElementById('search-submit').addEventListener('click', () => table.ajax.reload());

                Object.entries(exportUrls).forEach(([format, url]) => {
                    document.getElementById(`export-${format}`).addEventListener('click', function (e) {
                        e.preventDefault();
                        window.location.href = `${url}?${currentParams()}`;
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
