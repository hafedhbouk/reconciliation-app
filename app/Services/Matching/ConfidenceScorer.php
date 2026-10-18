<?php

namespace App\Services\Matching;

/**
 * Calcule le « score de confiance » (sur 100) d'un rapprochement automatique.
 * - 100 : montant ET date identiques
 * -  85 : accepté grâce à une tolérance (petit écart de montant ou de jours)
 *
 * Fixed values, not a graduated formula: all 6 seeded matching rules use zero
 * tolerance, so the "partial" branch is dead code until an admin deliberately
 * loosens a rule's tolerance — inventing a weighted-penalty formula now would
 * be uncalibrated guesswork. Revisit once real Partial-confidence data exists
 * to calibrate against.
 */
class ConfidenceScorer
{
    public function score(bool $amountExact, bool $dateExact): float
    {
        // Opérateur ternaire : condition ? valeur_si_vrai : valeur_si_faux
        return ($amountExact && $dateExact) ? 100.00 : 85.00;
    }
}
