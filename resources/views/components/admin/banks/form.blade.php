@props(['bank' => null])

<div class="mb-3">
    <x-input-label for="code" :value="__('Code')" />
    <x-text-input id="code" name="code" type="text" :value="old('code', $bank?->code)" required autofocus />
    <x-input-error :messages="$errors->get('code')" />
</div>

<div class="mb-3">
    <x-input-label for="name" :value="__('Nom')" />
    <x-text-input id="name" name="name" type="text" :value="old('name', $bank?->name)" required />
    <x-input-error :messages="$errors->get('name')" />
</div>

<div class="mb-3">
    <x-input-label for="swift_code" :value="__('Code SWIFT')" />
    <x-text-input id="swift_code" name="swift_code" type="text" :value="old('swift_code', $bank?->swift_code)" />
    <x-input-error :messages="$errors->get('swift_code')" />
</div>

<div class="mb-3">
    <x-input-label for="notes" :value="__('Notes')" />
    <textarea id="notes" name="notes" class="form-control" rows="3">{{ old('notes', $bank?->notes) }}</textarea>
    <x-input-error :messages="$errors->get('notes')" />
</div>

<div class="mb-3 form-check form-switch">
    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $bank?->is_active ?? true))>
    <label class="form-check-label" for="is_active">{{ __('Actif') }}</label>
</div>

<div class="d-flex gap-2">
    <x-primary-button>{{ __('Enregistrer') }}</x-primary-button>
    <a href="{{ route('admin.banks.index') }}" class="btn btn-outline-secondary">{{ __('Annuler') }}</a>
</div>
