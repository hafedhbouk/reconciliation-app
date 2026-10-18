{{--
    Composant <x-application-logo> : le logo STEG. {{ $attributes }} recopie les attributs passés (class, style…).
--}}
<img src="{{ asset('images/steg-logo.png') }}" alt="{{ config('app.name') }}" {{ $attributes }}>
