<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Composant Blade <x-app-layout> : la mise en page des pages connectées
 * (menu latéral, barre du haut), définie dans resources/views/layouts/app.blade.php.
 * Une vue l'utilise ainsi : <x-app-layout> ...contenu de la page... </x-app-layout>.
 */
class AppLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
