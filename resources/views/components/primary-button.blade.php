{{--
    Composant <x-primary-button> : bouton principal d'envoi de formulaire.
--}}
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-primary']) }}>
    {{ $slot }}
</button>
