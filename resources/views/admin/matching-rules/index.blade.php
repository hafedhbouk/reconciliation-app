{{--
    Liste des règles de rapprochement, avec « Lancer tout » et « Comparer deux fichiers ».
    Tableau DataTables rempli par MatchingRuleController::data.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fs-4 fw-semibold mb-0">{{ __('Règles de rapprochement') }}</h2>
            <div class="d-flex gap-2">
                @can('matching-rules.update')
                    <form action="{{ route('admin.matching-rules.run-all') }}" method="POST" onsubmit="return confirm('Lancer toutes les règles actives, dans l\'ordre de priorité ?')">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="bi bi-play-circle me-1"></i>{{ __('Lancer tout') }}
                        </button>
                    </form>
                    <a href="{{ route('admin.matching-rules.maintenance') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-clipboard-data me-1"></i>{{ __('Contrôler doublons et non-rapprochés') }}
                    </a>
                @endcan
                @can('matching-rules.create')
                    <a href="{{ route('admin.matching-rules.create') }}" class="btn btn-dark btn-sm">
                        <i class="bi bi-plus-lg me-1"></i>{{ __('Nouvelle règle') }}
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    @can('matching-rules.update')
        <div class="card mb-3">
            <div class="card-header fw-semibold">{{ __('Lancer un rapprochement personnalisé') }}</div>
            <div class="card-body">
                <form action="{{ route('admin.matching-rules.run-ad-hoc') }}" method="POST" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-auto">
                        <label for="import_a_id" class="form-label small fw-semibold">{{ __('Fichier Source A') }}</label>
                        <select name="import_a_id" id="import_a_id" class="form-select form-select-sm" required>
                            <option value="">{{ __('Sélectionner...') }}</option>
                            @foreach ($sources as $source)
                                @php $imports = $source->imports()->where('status', 'completed')->orderByDesc('created_at')->get(); @endphp
                                @if ($imports->isNotEmpty())
                                    <optgroup label="{{ $source->name }} ({{ $source->code }})">
                                        @foreach ($imports as $import)
                                            <option value="{{ $import->id }}">{{ $import->original_filename }} — @datetime($import->created_at)</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <label for="import_b_id" class="form-label small fw-semibold">{{ __('Fichier Source B') }}</label>
                        <select name="import_b_id" id="import_b_id" class="form-select form-select-sm" required>
                            <option value="">{{ __('Sélectionner...') }}</option>
                            @foreach ($sources as $source)
                                @php $imports = $source->imports()->where('status', 'completed')->orderByDesc('created_at')->get(); @endphp
                                @if ($imports->isNotEmpty())
                                    <optgroup label="{{ $source->name }} ({{ $source->code }})">
                                        @foreach ($imports as $import)
                                            <option value="{{ $import->id }}">{{ $import->original_filename }} — @datetime($import->created_at)</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-play-circle me-1"></i>{{ __('Démarrer le rapprochement') }}
                        </button>
                    </div>
                    @if ($emailRecipients->isNotEmpty())
                        {{-- Envoi des résultats par e-mail à la fin du rapprochement (App\Services\ResultEmails). --}}
                        <div class="col-12">
                            <div class="form-check">
                                <input type="hidden" name="email_envoyer" value="0">
                                <input class="form-check-input" type="checkbox" name="email_envoyer" value="1" id="email_envoyer"
                                       data-bs-toggle="collapse" data-bs-target="#email-options" aria-controls="email-options"
                                       aria-expanded="{{ old('email_envoyer') ? 'true' : 'false' }}" @checked(old('email_envoyer'))>
                                <label class="form-check-label small fw-semibold" for="email_envoyer">{{ __('Envoyer les résultats par e-mail à la fin du rapprochement') }}</label>
                            </div>
                            <div class="collapse {{ old('email_envoyer') ? 'show' : '' }} mt-2" id="email-options">
                                @include('admin.operations._email-recipients', ['recipients' => $emailRecipients, 'canAttach' => $canAttachResults])
                            </div>
                        </div>
                    @endif
                    @if ($errors->hasAny(['email_envoyer', 'email_destinataires', 'email_destinataires.*']))
                        <div class="col-12 small text-danger">{{ $errors->first('email_envoyer') ?: ($errors->first('email_destinataires') ?: $errors->first('email_destinataires.*')) }}</div>
                    @endif
                </form>
            </div>
        </div>
    @endcan

    <div class="card">
        <div class="table-responsive">
            <table id="matching-rules-table" class="table table-hover mb-0 align-middle w-100">
                <thead>
                    <tr>
                        <th>{{ __('Nom') }}</th>
                        <th>{{ __('Source A') }}</th>
                        <th>{{ __('Source B') }}</th>
                        <th>{{ __('Cardinalité') }}</th>
                        <th>{{ __('Priorité') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                $('#matching-rules-table').DataTable({
                    processing: true,
                    serverSide: true,
                    order: [[4, 'asc']],
                    ajax: '{{ route('admin.matching-rules.data') }}',
                    columns: [
                        { data: 'name', name: 'name' },
                        { data: 'source_a', name: 'sourceA.code' },
                        { data: 'source_b', name: 'sourceB.code' },
                        { data: 'cardinality_label', name: 'cardinality' },
                        { data: 'priority', name: 'priority' },
                        { data: 'is_active_label', name: 'is_active' },
                        { data: 'actions', name: 'actions', orderable: false, searchable: false },
                    ],
                });
            });
        </script>
    @endpush
</x-app-layout>
