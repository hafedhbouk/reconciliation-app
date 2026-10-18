<?php

namespace App\DataTransferObjects;

/**
 * Résumé renvoyé par DuplicateDetector après une recherche de doublons.
 * (Voir BankData pour l'explication de `readonly` et des DTO.)
 */
readonly class DuplicateScanSummary
{
    public function __construct(
        public int $groupsFound,       // nombre de groupes de transactions identiques trouvés
        public int $exceptionsCreated, // nombre d'exceptions « Doublon » créées pour ces groupes
    ) {}
}
