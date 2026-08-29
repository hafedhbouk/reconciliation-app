<?php

namespace App\Policies;

/**
 * Politique d'autorisation pour le modèle Import.
 *
 * Vérifie les permissions préfixées par "imports"
 * (ex: imports.viewAny, imports.delete) via BasePolicy.
 */

class ImportPolicy extends BasePolicy
{
    protected string $resource = 'imports';
}
