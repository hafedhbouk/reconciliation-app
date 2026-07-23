<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Association des colonnes') }} — {{ $source->code }}</h2>
    </x-slot>

    @if (session('status'))
        <div class="alert alert-info">{{ session('status') }}</div>
    @endif

    @if ($detectedHeaders !== [])
        <div class="card mb-3">
            <div class="card-header fw-semibold">{{ __('En-têtes détectés dans le dernier fichier') }}</div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($detectedHeaders as $header)
                        <span class="badge bg-secondary font-monospace">{{ $header }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-warning">
            {{ __('Aucun fichier de référence trouvé pour cette source — les colonnes ci-dessous doivent être saisies manuellement.') }}
        </div>
    @endif

    <datalist id="detected-headers">
        @foreach ($detectedHeaders as $header)
            <option value="{{ $header }}"></option>
        @endforeach
    </datalist>

    <form method="POST" action="{{ route('admin.sources.mappings.update', $source) }}">
        @csrf
        @method('PUT')

        @if ($importId)
            <input type="hidden" name="import_id" value="{{ $importId }}">
        @endif

        @foreach ($targetFields as $field)
            @php
                $mapping = $mappings->get($field->value);
                $steps = $mapping?->transform ?? [];
                $secondStep = $steps[1] ?? null;
                $secondStepKey = $secondStep['key'] ?? '';
                $secondStepConfig = $secondStep['config'] ?? [];
            @endphp
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3 align-items-start">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">{{ $field->label() }}</label>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="required-{{ $field->value }}"
                                    name="mappings[{{ $field->value }}][is_required]" value="1"
                                    @checked($mapping?->is_required)>
                                <label class="form-check-label small" for="required-{{ $field->value }}">{{ __('Obligatoire') }}</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small">{{ __('Colonne source (nom exact)') }}</label>
                            <input type="text" class="form-control" list="detected-headers"
                                name="mappings[{{ $field->value }}][source_column]"
                                value="{{ old("mappings.{$field->value}.source_column", $mapping?->source_column) }}"
                                placeholder="{{ __('— non mappé —') }}">
                        </div>

                        <div class="col-md-5">
                            <label class="form-label small">{{ __('Transformation') }}</label>
                            <select class="form-select form-select-sm mb-2" name="mappings[{{ $field->value }}][transform_type]">
                                <option value="">{{ __('Aucune (trim uniquement)') }}</option>
                                @foreach ($transformTypes as $type)
                                    @continue($type->value === 'trim')
                                    <option value="{{ $type->value }}" @selected($secondStepKey === $type->value)>{{ $type->label() }}</option>
                                @endforeach
                            </select>

                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="text" class="form-control form-control-sm" name="mappings[{{ $field->value }}][chars]"
                                        placeholder="{{ __('Caractères, ex: B,b') }}"
                                        value="{{ implode(',', $secondStepConfig['chars'] ?? []) }}">
                                </div>
                                <div class="col-6">
                                    <input type="text" class="form-control form-control-sm" name="mappings[{{ $field->value }}][date_format]"
                                        placeholder="{{ __('Format, ex: d/m/Y H:i:s') }}"
                                        value="{{ $secondStepConfig['format'] ?? '' }}">
                                </div>
                                <div class="col-4">
                                    <input type="text" class="form-control form-control-sm" name="mappings[{{ $field->value }}][delimiter]"
                                        placeholder="{{ __('Séparateur') }}"
                                        value="{{ $secondStepConfig['delimiter'] ?? '' }}">
                                </div>
                                <div class="col-4">
                                    <input type="number" min="1" class="form-control form-control-sm" name="mappings[{{ $field->value }}][n]"
                                        placeholder="{{ __('N-ième') }}"
                                        value="{{ $secondStepConfig['n'] ?? '' }}">
                                </div>
                                <div class="col-4">
                                    <input type="number" min="1" class="form-control form-control-sm" name="mappings[{{ $field->value }}][length]"
                                        placeholder="{{ __('Longueur') }}"
                                        value="{{ $secondStepConfig['length'] ?? '' }}">
                                </div>
                                <div class="col-4">
                                    <input type="number" min="0" max="6" class="form-control form-control-sm" name="mappings[{{ $field->value }}][decimals]"
                                        placeholder="{{ __('Décimales') }}"
                                        value="{{ $secondStepConfig['decimals'] ?? '' }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="d-flex gap-2">
            <x-primary-button>{{ __('Enregistrer le mapping') }}</x-primary-button>
            <a href="{{ $importId ? route('admin.imports.show', $importId) : route('admin.sources.edit', $source) }}" class="btn btn-outline-secondary">
                {{ __('Annuler') }}
            </a>
        </div>
    </form>
</x-app-layout>
