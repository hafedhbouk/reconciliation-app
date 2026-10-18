<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Composant Blade <x-guest-layout> : la mise en page des pages publiques
 * (connexion, mot de passe oublié), définie dans resources/views/layouts/guest.blade.php.
 */
class GuestLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.guest');
    }
}
