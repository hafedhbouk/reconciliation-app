{{--
    Composant <x-secondary-button> : bouton secondaire (ex. Annuler).
--}}
<button {{ $attributes->merge(['type' => 'button', 'class' => 'btn btn-outline-secondary']) }}>
    {{ $slot }}
</button>
