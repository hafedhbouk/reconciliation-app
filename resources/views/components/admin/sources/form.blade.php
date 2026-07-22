@props(['source' => null, 'banks' => [], 'currencies' => []])

<div class="mb-3">
    <x-input-label for="code" :value="__('Code')" />
    <x-text-input id="code" name="code" type="text" :value="old('code', $source?->code)" required autofocus />
    <x-input-error :messages="$errors->get('code')" />
</div>

<div class="mb-3">
    <x-input-label for="name" :value="__('Nom')" />
    <x-text-input id="name" name="name" type="text" :value="old('name', $source?->name)" required />
    <x-input-error :messages="$errors->get('name')" />
</div>

<div class="mb-3">
    <x-input-label for="bank_id" :value="__('Banque')" />
    <select id="bank_id" name="bank_id" class="form-select">
        <option value="">{{ __('— Aucune —') }}</option>
        @foreach ($banks as $bank)
            <option value="{{ $bank->id }}" @selected(old('bank_id', $source?->bank_id) == $bank->id)>{{ $bank->name }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('bank_id')" />
</div>

<div class="mb-3">
    <x-input-label for="file_type" :value="__('Type de fichier')" />
    <select id="file_type" name="file_type" class="form-select" required>
        @foreach (['csv' => 'CSV', 'xls' => 'XLS', 'xlsx' => 'XLSX'] as $value => $label)
            <option value="{{ $value }}" @selected(old('file_type', $source?->file_type) === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('file_type')" />
</div>

<div class="mb-3">
    <x-input-label for="default_currency_id" :value="__('Devise par défaut')" />
    <select id="default_currency_id" name="default_currency_id" class="form-select">
        <option value="">{{ __('— Aucune —') }}</option>
        @foreach ($currencies as $currency)
            <option value="{{ $currency->id }}" @selected(old('default_currency_id', $source?->default_currency_id) == $currency->id)>{{ $currency->iso_code }} — {{ $currency->name }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('default_currency_id')" />
</div>

<div class="mb-3">
    <x-input-label for="description" :value="__('Description')" />
    <textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $source?->description) }}</textarea>
    <x-input-error :messages="$errors->get('description')" />
</div>

<div class="mb-3 form-check form-switch">
    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $source?->is_active ?? true))>
    <label class="form-check-label" for="is_active">{{ __('Actif') }}</label>
</div>

<div class="d-flex gap-2">
    <x-primary-button>{{ __('Enregistrer') }}</x-primary-button>
    <a href="{{ route('admin.sources.index') }}" class="btn btn-outline-secondary">{{ __('Annuler') }}</a>
</div>
