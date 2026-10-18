{{--
    Composant <x-input-label> : étiquette (label) d'un champ de formulaire.
--}}
@props(['value'])

<label {{ $attributes->merge(['class' => 'form-label']) }}>
    {{ $value ?? $slot }}
</label>
