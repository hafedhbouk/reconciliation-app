<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Modifier le paramètre') }}</h2>
    </x-slot>

    <div class="card" style="max-width: 40rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.settings.update', $setting) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <x-input-label :value="__('Clé')" />
                    <p class="font-monospace">{{ $setting->group }}.{{ $setting->key }}</p>
                </div>

                @if ($setting->description)
                    <p class="small text-secondary">{{ $setting->description }}</p>
                @endif

                <div class="mb-3">
                    <x-input-label for="value" :value="__('Valeur')" />
                    @if ($setting->type === 'boolean')
                        <select id="value" name="value" class="form-select">
                            <option value="1" @selected(old('value', $setting->value))>{{ __('Oui') }}</option>
                            <option value="0" @selected(!old('value', $setting->value))>{{ __('Non') }}</option>
                        </select>
                    @else
                        <x-text-input id="value" name="value" type="text" :value="old('value', $setting->value)" required autofocus />
                    @endif
                    <x-input-error :messages="$errors->get('value')" />
                </div>

                <div class="d-flex gap-2">
                    <x-primary-button>{{ __('Enregistrer') }}</x-primary-button>
                    <a href="{{ route('admin.settings.index') }}" class="btn btn-outline-secondary">{{ __('Annuler') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
