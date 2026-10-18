{{--
    Composant <x-danger-button> : bouton rouge. $attributes->merge() ajoute les classes par défaut aux attributs donnés.
--}}
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-danger']) }}>
    {{ $slot }}
</button>
