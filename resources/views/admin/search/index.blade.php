<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fs-4 fw-semibold mb-0">{{ __('Recherche multi-critères') }}</h2>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.search.exports') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-folder2-open me-1"></i>{{ __('Mes exports') }}</a>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#exportModal">
                    <i class="bi bi-download me-1"></i>{{ __('Exporter') }}
                </button>
            </div>
        </div>
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
                            @if (! in_array($case->value, ['ignored', 'partial']))
                                <option value="{{ $case->value }}">{{ $case->label() }}</option>
                            @endif
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

    {{-- Modal d'export asynchrone --}}
    <div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportModalLabel">{{ __('Exporter les résultats') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Fermer') }}"></button>
                </div>
                <form method="POST" action="{{ route('admin.search.export-async') }}">
                    @csrf
                    <div class="modal-body">
                        <p class="text-muted small">{{ __('L\'export sera généré en arrière-plan. Vous serez notifié une fois le fichier prêt.') }}</p>

                        <div class="mb-3">
                            <label for="export_format" class="form-label">{{ __('Format') }}</label>
                            <select name="format" id="export_format" class="form-select" required>
                                <option value="csv">CSV</option>
                                <option value="xlsx">Excel (XLSX)</option>
                                <option value="pdf">PDF</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="export_source" class="form-label">{{ __('Source (optionnel)') }}</label>
                            <select name="source_id" id="export_source" class="form-select">
                                <option value="">{{ __('Toutes les sources') }}</option>
                                @foreach ($sources as $source)
                                    <option value="{{ $source->id }}">{{ $source->code }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="export_reference" class="form-label">{{ __('Référence (optionnel)') }}</label>
                            <input type="text" name="reference" id="export_reference" class="form-control" value="{{ request('reference') }}">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="export_amount_min" class="form-label">{{ __('Montant min (optionnel)') }}</label>
                                <input type="number" name="amount_min" id="export_amount_min" class="form-control" value="{{ request('amount_min') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="export_amount_max" class="form-label">{{ __('Montant max (optionnel)') }}</label>
                                <input type="number" name="amount_max" id="export_amount_max" class="form-control" value="{{ request('amount_max') }}">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="export_date_from" class="form-label">{{ __('Date de début (optionnel)') }}</label>
                                <input type="date" name="date_from" id="export_date_from" class="form-control" value="{{ request('date_from') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="export_date_to" class="form-label">{{ __('Date de fin (optionnel)') }}</label>
                                <input type="date" name="date_to" id="export_date_to" class="form-control" value="{{ request('date_to') }}">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="export_canal" class="form-label">{{ __('Canal (optionnel)') }}</label>
                            <input type="text" name="canal" id="export_canal" class="form-control" value="{{ request('canal') }}">
                        </div>

                        <div class="mb-3">
                            <label for="export_status" class="form-label">{{ __('Statut (optionnel)') }}</label>
                            <select name="matching_status" id="export_status" class="form-select">
                                <option value="">{{ __('Tous les statuts') }}</option>
                                @foreach (\App\Enums\MatchingStatus::cases() as $case)
                                    @if (! in_array($case->value, ['ignored', 'partial']))
                                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Annuler') }}</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-download me-1"></i>{{ __('Lancer l\'export') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
