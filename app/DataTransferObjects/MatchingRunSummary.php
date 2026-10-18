<?php

namespace App\DataTransferObjects;

/**
 * Résumé chiffré d'une exécution de rapprochement (RuleMatcher::match()).
 *
 * Il est affiché à l'utilisateur et enregistré dans comparison_runs.summary.
 * (Voir BankData pour l'explication de `readonly` et des DTO.)
 */
readonly class MatchingRunSummary
{
    public function __construct(
        public int $referencesConsidered, // nombre de clés (références) examinées
        public int $matched,              // groupes rapprochés avec succès
        public int $conflicts,            // groupes avec la bonne clé mais un montant ou une date différent
        public int $noSignal,             // clé commune mais rien ne correspond : on ne fait rien
        public int $skipped = 0,          // groupes ignorés car déjà traités par un autre job en parallèle
        public int $unmatchedA = 0,       // lignes présentes seulement dans le fichier A
        public int $unmatchedB = 0,       // lignes présentes seulement dans le fichier B
    ) {}
}
