<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Connexion et déconnexion.
 *
 * Les contrôleurs du dossier Auth viennent de Laravel Breeze (kit
 * d'authentification officiel) : connexion, inscription, mot de passe
 * oublié, vérification d'email. Ils sont standards et rarement modifiés.
 */
class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // Vérifie email + mot de passe (voir LoginRequest::authenticate), sinon renvoie une erreur.
        $request->authenticate();

        // Nouvel identifiant de session après connexion : protège contre le « vol de session ».
        $request->session()->regenerate();

        // intended() : retourne à la page demandée avant la connexion, sinon au tableau de bord.
        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
