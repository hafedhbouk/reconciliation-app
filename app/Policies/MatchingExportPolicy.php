<?php

namespace App\Policies;

use App\Models\MatchingExport;

/**
 * Politique d'autorisation pour MatchingExport.
 *
 * Vérifie les permissions préfixées par "matching-exports".
 */
class MatchingExportPolicy extends BasePolicy
{
    protected string $resource = 'matching-exports';
}
