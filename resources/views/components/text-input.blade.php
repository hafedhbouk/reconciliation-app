{{--
    Composant <x-text-input> : champ texte avec le style Bootstrap.
--}}
@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'form-control']) }}>
