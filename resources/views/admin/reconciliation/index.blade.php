<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Rapprochement manuel') }}</h2>
    </x-slot>

    <form id="manual-match-form" method="POST" action="{{ route('admin.reconciliation.store') }}">
        @csrf
        <div id="manual-match-hidden-inputs"></div>

        <div class="row">
            @foreach (['a' => __('Côté A'), 'b' => __('Côté B')] as $side => $label)
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header fw-semibold">{{ $label }}</div>
                        <div class="card-body">
                            <div class="row g-2 mb-2">
                                <div class="col-md-4">
                                    <select class="form-select form-select-sm" data-panel="{{ $side }}" data-field="source_id">
                                        <option value="">{{ __('Toutes les sources') }}</option>
                                        @foreach ($sources as $source)
                                            <option value="{{ $source->id }}">{{ $source->code }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" class="form-control form-control-sm" data-panel="{{ $side }}" data-field="reference" placeholder="{{ __('Référence') }}">
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-sm btn-outline-primary w-100" data-search="{{ $side }}">
                                        <i class="bi bi-search me-1"></i>{{ __('Rechercher') }}
                                    </button>
                                </div>
                                <div class="col-12 d-flex gap-2 align-items-center">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-select-all="{{ $side }}">
                                        <i class="bi bi-check2-square me-1"></i>{{ __('Tout sélectionner') }}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-deselect-all="{{ $side }}">
                                        {{ __('Tout désélectionner') }}
                                    </button>
                                    <span class="small text-secondary" data-select-all-status="{{ $side }}"></span>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" class="form-control form-control-sm" data-panel="{{ $side }}" data-field="amount_min" placeholder="{{ __('Montant min') }}">
                                </div>
                                <div class="col-md-3">
                                    <input type="number" class="form-control form-control-sm" data-panel="{{ $side }}" data-field="amount_max" placeholder="{{ __('Montant max') }}">
                                </div>
                                <div class="col-md-3">
                                    <input type="date" class="form-control form-control-sm" data-panel="{{ $side }}" data-field="date_from">
                                </div>
                                <div class="col-md-3">
                                    <input type="date" class="form-control form-control-sm" data-panel="{{ $side }}" data-field="date_to">
                                </div>
                            </div>

                            <div class="table-responsive" style="max-height: 24rem; overflow-y: auto;">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" class="form-check-input" data-select-page="{{ $side }}" aria-label="{{ __('Sélectionner toute la page') }}"></th>
                                            <th>{{ __('Source') }}</th>
                                            <th>{{ __('Référence') }}</th>
                                            <th>{{ __('Montant') }}</th>
                                            <th>{{ __('Date') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody data-results="{{ $side }}">
                                        <tr><td colspan="5" class="text-center text-secondary py-3">{{ __('Lancez une recherche.') }}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <nav data-pagination="{{ $side }}" class="mt-2"></nav>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-dark" id="create-match-btn" disabled>
                {{ __('Créer un rapprochement') }} (<span id="selected-count">0</span> {{ __('sélectionnés') }})
            </button>
        </div>
    </form>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const searchUrl = '{{ route('admin.reconciliation.search') }}';
                const selected = { a: new Set(), b: new Set() };
                const currentPage = { a: 1, b: 1 };
                const selectAllDoneText = @json(__(':count sélectionné(s).'));
                const selectAllTruncatedText = @json(__(':count sélectionné(s) (limite atteinte, affinez la recherche pour tout inclure).'));

                function fieldsFor(side) {
                    const params = new URLSearchParams();
                    document.querySelectorAll(`[data-panel="${side}"]`).forEach((el) => {
                        if (el.value) params.set(el.dataset.field, el.value);
                    });
                    return params;
                }

                function syncPageCheckbox(side) {
                    const boxes = Array.from(document.querySelectorAll(`[data-select="${side}"]`));
                    const pageCheckbox = document.querySelector(`[data-select-page="${side}"]`);
                    if (!pageCheckbox) return;
                    const checkedCount = boxes.filter((b) => b.checked).length;
                    pageCheckbox.checked = boxes.length > 0 && checkedCount === boxes.length;
                    pageCheckbox.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
                }

                function renderResults(side, page = 1) {
                    const params = fieldsFor(side);
                    params.set('page', page);
                    currentPage[side] = page;

                    fetch(`${searchUrl}?${params.toString()}`, { headers: { Accept: 'application/json' } })
                        .then((r) => r.json())
                        .then((json) => {
                            const tbody = document.querySelector(`[data-results="${side}"]`);
                            tbody.innerHTML = '';

                            if (json.data.length === 0) {
                                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-secondary py-3">{{ __('Aucun résultat.') }}</td></tr>';
                            }

                            json.data.forEach((row) => {
                                const tr = document.createElement('tr');
                                const checked = selected[side].has(row.id) ? 'checked' : '';
                                tr.innerHTML = `
                                    <td><input type="checkbox" class="form-check-input" data-select="${side}" value="${row.id}" ${checked}></td>
                                    <td>${row.source ?? ''}</td>
                                    <td>${row.reference ?? ''}</td>
                                    <td>${row.amount_millimes ?? ''}</td>
                                    <td>${row.date ?? ''}</td>
                                `;
                                tbody.appendChild(tr);
                            });

                            const nav = document.querySelector(`[data-pagination="${side}"]`);
                            nav.innerHTML = '';
                            if (json.last_page > 1) {
                                for (let p = 1; p <= json.last_page; p++) {
                                    const btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = `btn btn-sm ${p === json.current_page ? 'btn-secondary' : 'btn-outline-secondary'} me-1`;
                                    btn.textContent = p;
                                    btn.addEventListener('click', () => renderResults(side, p));
                                    nav.appendChild(btn);
                                }
                            }

                            syncPageCheckbox(side);
                        });
                }

                document.querySelectorAll('[data-search]').forEach((btn) => {
                    btn.addEventListener('click', () => renderResults(btn.dataset.search, 1));
                });

                document.querySelectorAll('[data-select-page]').forEach((box) => {
                    box.addEventListener('change', () => {
                        const side = box.dataset.selectPage;
                        document.querySelectorAll(`[data-select="${side}"]`).forEach((rowBox) => {
                            rowBox.checked = box.checked;
                            const id = parseInt(rowBox.value, 10);
                            if (box.checked) {
                                selected[side].add(id);
                            } else {
                                selected[side].delete(id);
                            }
                        });
                        updateSelectionUi();
                    });
                });

                document.querySelectorAll('[data-select-all]').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const side = btn.dataset.selectAll;
                        const status = document.querySelector(`[data-select-all-status="${side}"]`);
                        const params = fieldsFor(side);
                        params.set('all', '1');

                        btn.disabled = true;
                        fetch(`${searchUrl}?${params.toString()}`, { headers: { Accept: 'application/json' } })
                            .then((r) => r.json())
                            .then((json) => {
                                json.ids.forEach((id) => selected[side].add(id));
                                status.textContent = (json.truncated ? selectAllTruncatedText : selectAllDoneText)
                                    .replace(':count', json.ids.length);
                                renderResults(side, currentPage[side]);
                                updateSelectionUi();
                            })
                            .finally(() => { btn.disabled = false; });
                    });
                });

                document.querySelectorAll('[data-deselect-all]').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const side = btn.dataset.deselectAll;
                        selected[side].clear();
                        document.querySelector(`[data-select-all-status="${side}"]`).textContent = '';
                        renderResults(side, currentPage[side]);
                        updateSelectionUi();
                    });
                });

                document.body.addEventListener('change', (e) => {
                    if (!e.target.matches('[data-select]')) return;
                    const side = e.target.dataset.select;
                    const id = parseInt(e.target.value, 10);
                    if (e.target.checked) {
                        selected[side].add(id);
                    } else {
                        selected[side].delete(id);
                    }
                    syncPageCheckbox(side);
                    updateSelectionUi();
                });

                function updateSelectionUi() {
                    const total = selected.a.size + selected.b.size;
                    document.getElementById('selected-count').textContent = total;
                    document.getElementById('create-match-btn').disabled = selected.a.size === 0 || selected.b.size === 0;
                }

                document.getElementById('manual-match-form').addEventListener('submit', () => {
                    const container = document.getElementById('manual-match-hidden-inputs');
                    container.innerHTML = '';
                    ['a', 'b'].forEach((side) => {
                        selected[side].forEach((id) => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = `normalized_transaction_ids_${side}[]`;
                            input.value = id;
                            container.appendChild(input);
                        });
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
