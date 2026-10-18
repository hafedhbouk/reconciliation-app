<?php

namespace App\Enums;

/**
 * Statut d'un résultat de rapprochement (table matching_results).
 *
 * Un résultat relie un groupe de transactions de la source A à un groupe
 * de la source B. Ne pas confondre avec MatchingStatus, qui est le statut
 * d'UNE transaction.
 * (Voir ExceptionStatus pour l'explication des enums.)
 */
enum MatchingResultStatus: string
{
    case Matched = 'matched';   // montant et date identiques : rapprochement parfait
    case Partial = 'partial';   // accepté grâce à la tolérance (petit écart de montant ou de jours)
    case Conflict = 'conflict'; // même référence mais écart hors tolérance : une exception est créée
    case Rejected = 'rejected'; // rapprochement annulé par un utilisateur

    /** Libellé français affiché dans l'interface. */
    public function label(): string
    {
        return match ($this) {
            self::Matched => 'Rapproché',
            self::Partial => 'Partiel',
            self::Conflict => 'Conflit',
            self::Rejected => 'Rejeté',
        };
    }

    /** Classe CSS Bootstrap du badge coloré. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Matched => 'bg-success',
            self::Partial => 'bg-warning text-dark',
            self::Conflict => 'bg-danger',
            self::Rejected => 'bg-secondary',
        };
    }
}
