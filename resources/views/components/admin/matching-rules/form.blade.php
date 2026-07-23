@props(['rule' => null, 'sources' => []])

@php
    $criteria = $rule?->criteria ?? [];
    $excludedA = implode(', ', $criteria['excluded_status_raw']['a'] ?? []);
    $excludedB = implode(', ', $criteria['excluded_status_raw']['b'] ?? []);
@endphp

<div class="mb-3">
    <x-input-label for="name" :value="__('Nom')" />
    <x-text-input id="name" name="name" type="text" :value="old('name', $rule?->name)" required autofocus />
    <x-input-error :messages="$errors->get('name')" />
</div>

<div class="mb-3">
    <x-input-label for="description" :value="__('Description')" />
    <textarea id="description" name="description" class="form-control" rows="2">{{ old('description', $rule?->description) }}</textarea>
    <x-input-error :messages="$errors->get('description')" />
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <x-input-label for="source_a_id" :value="__('Source A')" />
        <select id="source_a_id" name="source_a_id" class="form-select" required>
            <option value="">{{ __('— Choisir —') }}</option>
            @foreach ($sources as $source)
                <option value="{{ $source->id }}" @selected(old('source_a_id', $rule?->source_a_id) == $source->id)>
                    {{ $source->code }} — {{ $source->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('source_a_id')" />
    </div>

    <div class="col-md-6 mb-3">
        <x-input-label for="source_b_id" :value="__('Source B')" />
        <select id="source_b_id" name="source_b_id" class="form-select" required>
            <option value="">{{ __('— Choisir —') }}</option>
            @foreach ($sources as $source)
                <option value="{{ $source->id }}" @selected(old('source_b_id', $rule?->source_b_id) == $source->id)>
                    {{ $source->code }} — {{ $source->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('source_b_id')" />
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <x-input-label for="cardinality" :value="__('Cardinalité')" />
        <select id="cardinality" name="cardinality" class="form-select" required>
            @foreach (\App\Enums\MatchingCardinality::cases() as $case)
                <option value="{{ $case->value }}" @selected(old('cardinality', $rule?->cardinality?->value ?? 'N:M') === $case->value)>
                    {{ $case->value }} — {{ $case->label() }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('cardinality')" />
    </div>

    <div class="col-md-4 mb-3">
        <x-input-label for="priority" :value="__('Priorité')" />
        <x-text-input id="priority" name="priority" type="number" min="0" :value="old('priority', $rule?->priority ?? 0)" required />
        <x-input-error :messages="$errors->get('priority')" />
    </div>

    <div class="col-md-4 mb-3 d-flex align-items-end">
        <div class="form-check form-switch">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $rule?->is_active ?? true))>
            <label class="form-check-label" for="is_active">{{ __('Actif') }}</label>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <x-input-label for="tolerance_amount_millimes" :value="__('Tolérance montant (millimes)')" />
        <x-text-input id="tolerance_amount_millimes" name="tolerance_amount_millimes" type="number" min="0" :value="old('tolerance_amount_millimes', $criteria['tolerance_amount_millimes'] ?? 0)" required />
        <x-input-error :messages="$errors->get('tolerance_amount_millimes')" />
    </div>

    <div class="col-md-6 mb-3">
        <x-input-label for="tolerance_days" :value="__('Tolérance jours')" />
        <x-text-input id="tolerance_days" name="tolerance_days" type="number" min="0" :value="old('tolerance_days', $criteria['tolerance_days'] ?? 0)" required />
        <x-input-error :messages="$errors->get('tolerance_days')" />
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <x-input-label for="excluded_status_raw_a" :value="__('Statuts exclus — Source A')" />
        <x-text-input id="excluded_status_raw_a" name="excluded_status_raw_a" type="text" :value="old('excluded_status_raw_a', $excludedA)" placeholder="Commission, ..." />
        <div class="form-text">{{ __('Liste séparée par des virgules (ex : Commission).') }}</div>
        <x-input-error :messages="$errors->get('excluded_status_raw_a')" />
    </div>

    <div class="col-md-6 mb-3">
        <x-input-label for="excluded_status_raw_b" :value="__('Statuts exclus — Source B')" />
        <x-text-input id="excluded_status_raw_b" name="excluded_status_raw_b" type="text" :value="old('excluded_status_raw_b', $excludedB)" placeholder="Commission, ..." />
        <div class="form-text">{{ __('Liste séparée par des virgules (ex : Commission).') }}</div>
        <x-input-error :messages="$errors->get('excluded_status_raw_b')" />
    </div>
</div>

<div class="d-flex gap-2">
    <x-primary-button>{{ __('Enregistrer') }}</x-primary-button>
    <a href="{{ route('admin.matching-rules.index') }}" class="btn btn-outline-secondary">{{ __('Annuler') }}</a>
</div>
