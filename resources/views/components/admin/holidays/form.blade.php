@props(['holiday' => null])

<div class="mb-3">
    <x-input-label for="holiday_date" :value="__('Date')" />
    <x-text-input id="holiday_date" name="holiday_date" type="date" :value="old('holiday_date', $holiday?->holiday_date?->format('Y-m-d'))" required autofocus />
    <x-input-error :messages="$errors->get('holiday_date')" />
</div>

<div class="mb-3">
    <x-input-label for="name" :value="__('Nom')" />
    <x-text-input id="name" name="name" type="text" :value="old('name', $holiday?->name)" required />
    <x-input-error :messages="$errors->get('name')" />
</div>

<div class="mb-3">
    <x-input-label for="country_code" :value="__('Code pays')" />
    <x-text-input id="country_code" name="country_code" type="text" maxlength="2" :value="old('country_code', $holiday?->country_code ?? 'TN')" required />
    <x-input-error :messages="$errors->get('country_code')" />
</div>

<div class="mb-3 form-check form-switch">
    <input type="checkbox" class="form-check-input" id="is_recurring_yearly" name="is_recurring_yearly" value="1" @checked(old('is_recurring_yearly', $holiday?->is_recurring_yearly ?? false))>
    <label class="form-check-label" for="is_recurring_yearly">{{ __('Récurrent chaque année') }}</label>
</div>

<div class="d-flex gap-2">
    <x-primary-button>{{ __('Enregistrer') }}</x-primary-button>
    <a href="{{ route('admin.holidays.index') }}" class="btn btn-outline-secondary">{{ __('Annuler') }}</a>
</div>
