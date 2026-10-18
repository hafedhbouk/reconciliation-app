<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Classe parente de tous les contrôleurs de l'application.
 *
 * `abstract` : on ne l'utilise jamais directement, on en hérite
 * (class ImportController extends Controller).
 * Le trait AuthorizesRequests fournit $this->authorize(...) et
 * $this->authorizeResource(...), qui vérifient les droits via les Policies.
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
}
