@props(['user' => null, 'roles' => []])

<div class="mb-3">
    <x-input-label for="prenom" :value="__('Prénom')" />
    <x-text-input id="prenom" name="prenom" type="text" :value="old('prenom', $user?->prenom)" required autofocus />
    <x-input-error :messages="$errors->get('prenom')" />
</div>

<div class="mb-3">
    <x-input-label for="nom" :value="__('Nom')" />
    <x-text-input id="nom" name="nom" type="text" :value="old('nom', $user?->nom)" required />
    <x-input-error :messages="$errors->get('nom')" />
</div>

<div class="mb-3">
    <x-input-label for="matricule" :value="__('Matricule')" />
    <x-text-input id="matricule" name="matricule" type="text" :value="old('matricule', $user?->matricule)" required />
    <x-input-error :messages="$errors->get('matricule')" />
</div>

<div class="mb-3">
    <x-input-label for="name" :value="__('Nom complet')" />
    <x-text-input id="name" name="name" type="text" :value="old('name', $user?->name)" required />
    <x-input-error :messages="$errors->get('name')" />
</div>

<div class="mb-3">
    <x-input-label for="email" :value="__('Email')" />
    <x-text-input id="email" name="email" type="email" :value="old('email', $user?->email)" required />
    <x-input-error :messages="$errors->get('email')" />
</div>

<div class="mb-3">
    <x-input-label for="portable" :value="__('Numéro de portable')" />
    <x-text-input id="portable" name="portable" type="tel" :value="old('portable', $user?->portable)" />
    <x-input-error :messages="$errors->get('portable')" />
</div>

<div class="mb-3">
    <x-input-label for="password" :value="__($user ? 'Nouveau mot de passe (laisser vide pour ne pas changer)' : 'Mot de passe')" />
    <x-text-input id="password" name="password" type="password" autocomplete="new-password" :required="!$user" />
    <x-input-error :messages="$errors->get('password')" />
</div>

<div class="mb-3">
    <x-input-label :value="__('Rôles')" />
    <div class="d-flex flex-column gap-1">
        @foreach ($roles as $role)
            <div class="form-check">
                <input
                    type="checkbox"
                    class="form-check-input"
                    id="role_{{ $role->id }}"
                    name="roles[]"
                    value="{{ $role->name }}"
                    @checked(collect(old('roles', $user?->roles->pluck('name')->all() ?? []))->contains($role->name))
                >
                <label class="form-check-label" for="role_{{ $role->id }}">{{ $role->name }}</label>
            </div>
        @endforeach
    </div>
    <x-input-error :messages="$errors->get('roles')" />
</div>

<div class="mb-3 form-check form-switch">
    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $user?->is_active ?? true))>
    <label class="form-check-label" for="is_active">{{ __('Actif') }}</label>
</div>

<div class="d-flex gap-2">
    <x-primary-button>{{ __('Enregistrer') }}</x-primary-button>
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">{{ __('Annuler') }}</a>
</div>
