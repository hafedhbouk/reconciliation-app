{{--
    Mise en page commune à toutes les pages connectées : menu latéral, barre du haut,
    fil d'Ariane, message de confirmation, contenu de la page, pied de page.
    Utilisée via le composant <x-app-layout> (app/View/Components/AppLayout.php).

    =========================================================================
    GUIDE DÉBUTANT : la syntaxe Blade (valable pour tout resources/views)
    =========================================================================
    Blade est le moteur de gabarits de Laravel : du HTML enrichi de quelques balises.
      {{ $variable }}          affiche une valeur, en l'ÉCHAPPANT (protection contre l'injection de HTML)
      {!! $html !!}            affiche sans échapper : à éviter sauf pour du HTML sûr
      {{-- texte -- puis }}    commentaire Blade (fermé par deux tirets et deux accolades), jamais envoyé au navigateur
      @if / @elseif / @else / @endif     condition
      @foreach ($items as $item) ... @endforeach   boucle ($loop->first / $loop->last disponibles)
      @isset($x) ... @endisset           seulement si la variable existe
      @can('imports.create') ... @endcan affiche seulement si l'utilisateur a la permission
      @csrf                    champ caché obligatoire dans chaque formulaire POST (anti-CSRF)
      @method('DELETE')        simule DELETE/PUT/PATCH (un formulaire HTML ne connaît que GET et POST)
      @error('champ') ... @enderror      affiche l'erreur de validation d'un champ
      old('champ', $defaut)    valeur saisie précédemment (après une erreur de validation)
      __('Texte')              texte traduisible
      route('admin.imports.show', $import)   construit l'URL d'une route nommée
      <x-app-layout> ... </x-app-layout>     utilise un « composant » (resources/views/components)
      <x-slot name="header"> ... </x-slot>   remplit une zone nommée du composant
      {{ $slot }}              dans un composant : le contenu placé entre ses balises
      @props([...])            dans un composant : les paramètres qu'il accepte
      @push('scripts') ... @endpush  ajoute du JavaScript, affiché à l'endroit de @stack('scripts')
      @php ... @endphp         du PHP brut (à limiter : la logique va plutôt dans le contrôleur)
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- Jeton anti-CSRF, relu par le JavaScript pour les requêtes AJAX. --}}
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        {{-- Vite compile et inclut le CSS (Bootstrap + styles du projet) et le JavaScript. --}}
        @vite(['resources/css/app.scss', 'resources/js/app.js'])

        {{-- Applique le thème clair/sombre mémorisé AVANT l'affichage, pour éviter un flash. --}}
        <script>
            (function () {
                const theme = localStorage.getItem('theme') ||
                    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-bs-theme', theme);
            })();
        </script>
    </head>
    <body>
        <div class="app-shell">
            <x-admin.sidebar />

            <div class="app-main">
                <x-admin.topbar />

                <main class="app-content">
                    @isset($breadcrumbs)
                        <x-admin.breadcrumb :items="$breadcrumbs" />
                    @endisset

                    @isset($header)
                        <div class="page-header">
                            {{ $header }}
                        </div>
                    @endisset

                    {{-- Message « flash » envoyé par un contrôleur avec ->with('status', '...'). --}}
                    @if (session('status'))
                        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                            <div>{{ session('status') }}</div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Fermer') }}"></button>
                        </div>
                    @endif

                    {{-- Contenu propre à chaque page. --}}
                    {{ $slot }}
                </main>

                <footer class="app-footer">
                    <span>© 2026 HANDOURA Houcine — Tous droits réservés</span>
                    <span class="fw-semibold">Version 1.0</span>
                </footer>
            </div>
        </div>

        <div class="admin-sidebar-overlay" id="sidebarOverlay"></div>

        {{-- Chatbot métier : bouton flottant et fenêtre « Assistant » (resources/js/chatbot.js). --}}
        <x-chatbot />

        {{-- Sur mobile : ouverture et fermeture du menu latéral (bouton, voile, croix, touche Échap, lien choisi). --}}
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const sidebar = document.querySelector('.admin-sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                const toggleBtn = document.getElementById('mobileMenuToggle');
                if (!toggleBtn || !sidebar) {
                    return;
                }

                function setOpen(open) {
                    sidebar.classList.toggle('open', open);
                    overlay.classList.toggle('open', open);
                    document.body.classList.toggle('sidebar-open', open);
                    toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                }

                toggleBtn.addEventListener('click', () => setOpen(!sidebar.classList.contains('open')));
                overlay.addEventListener('click', () => setOpen(false));
                sidebar.querySelector('[data-sidebar-close]')?.addEventListener('click', () => setOpen(false));
                sidebar.querySelectorAll('a.sidebar-link').forEach((link) => link.addEventListener('click', () => setOpen(false)));
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        setOpen(false);
                    }
                });
            });
        </script>

        {{-- Scripts ajoutés par les pages avec @push('scripts'), ex. la configuration des tableaux DataTables. --}}
        @stack('scripts')
    </body>
</html>
