@props(['currency' => null])

<div class="mb-3">
    <x-input-label for="iso_code" :value="__('Code ISO')" />
    <x-text-input id="iso_code" name="iso_code" type="text" maxlength="3" :value="old('iso_code', $currency?->iso_code)" required autofocus />
    <x-input-error :messages="$errors->get('iso_code')" />
</div>

<div class="mb-3">
    <x-input-label for="name" :value="__('Nom')" />
    <x-text-input id="name" name="name" type="text" :value="old('name', $currency?->name)" required />
    <x-input-error :messages="$errors->get('name')" />
</div>

<div class="mb-3">
    <x-input-label for="symbol" :value="__('Symbole')" />
    <x-text-input id="symbol" name="symbol" type="text" :value="old('symbol', $currency?->symbol)" />
    <x-input-error :messages="$errors->get('symbol')" />
</div>

<div class="mb-3">
    <x-input-label for="decimal_places" :value="__('Décimales')" />
    <x-text-input id="decimal_places" name="decimal_places" type="number" min="0" max="6" :value="old('decimal_places', $currency?->decimal_places ?? 3)" required />
    <x-input-error :messages="$errors->get('decimal_places')" />
</div>

<div class="mb-3 form-check form-switch">
    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $currency?->is_active ?? true))>
    <label class="form-check-label" for="is_active">{{ __('Actif') }}</label>
</div>

<div class="d-flex gap-2">
    <x-primary-button>{{ __('Enregistrer') }}</x-primary-button>
    <a href="{{ route('admin.currencies.index') }}" class="btn btn-outline-secondary">{{ __('Annuler') }}</a>
</div>
