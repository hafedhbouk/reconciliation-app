<?php

namespace App\Policies;

/**
 * Politique d'autorisation pour MatchingResult.
 *
 * Vérifie les permissions préfixées par "matching-results"
 * (ex: matching-results.viewAny).
 */

class MatchingResultPolicy extends BasePolicy
{
    protected string $resource = 'matching-results';
}
