{{--
    Composant <x-admin.breadcrumb> : le fil d'Ariane (Accueil > Imports > …). Le dernier élément n'est pas un lien.
--}}
@props(['items' => []])

<nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb app-breadcrumb mb-0">
        @foreach ($items as $label => $url)
            @if ($loop->last || $url === null)
                <li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>
            @else
                <li class="breadcrumb-item">
                    <a href="{{ $url }}">@if ($loop->first)<i class="bi bi-house-door me-1" aria-hidden="true"></i>@endif{{ $label }}</a>
                </li>
            @endif
        @endforeach
    </ol>
</nav>
