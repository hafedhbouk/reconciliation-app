@props(['role' => null, 'permissions' => []])

@php
    $assigned = collect(old('permissions', $role?->permissions->pluck('name')->all() ?? []));
@endphp

<div class="mb-3">
    <x-input-label for="name" :value="__('Nom du rôle')" />
    <x-text-input id="name" name="name" type="text" :value="old('name', $role?->name)" required autofocus :disabled="in_array($role?->name, ['super-admin', 'admin'], true)" />
    <x-input-error :messages="$errors->get('name')" />
</div>

<div class="mb-3">
    <x-input-label :value="__('Permissions')" />
    @foreach ($permissions as $resource => $resourcePermissions)
        <div class="border rounded p-2 mb-2">
            <div class="fw-semibold text-uppercase small mb-1">{{ $resource }}</div>
            <div class="d-flex flex-wrap gap-3">
                @foreach ($resourcePermissions as $permission)
                    <div class="form-check">
                        <input
                            type="checkbox"
                            class="form-check-input"
                            id="permission_{{ $permission->id }}"
                            name="permissions[]"
                            value="{{ $permission->name }}"
                            @checked($assigned->contains($permission->name))
                        >
                        <label class="form-check-label" for="permission_{{ $permission->id }}">{{ $permission->name }}</label>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
    <x-input-error :messages="$errors->get('permissions')" />
</div>

<div class="d-flex gap-2">
    <x-primary-button>{{ __('Enregistrer') }}</x-primary-button>
    <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">{{ __('Annuler') }}</a>
</div>
