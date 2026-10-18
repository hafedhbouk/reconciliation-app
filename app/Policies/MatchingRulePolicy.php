<?php

namespace App\Policies;

/**
 * Droits sur les règles de rapprochement : permissions « matching-rules.* » (voir BasePolicy).
 * « update » sert aussi à autoriser le lancement des règles et de la maintenance.
 */
class MatchingRulePolicy extends BasePolicy
{
    protected string $resource = 'matching-rules';
}
