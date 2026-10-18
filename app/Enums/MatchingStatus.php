<?php

namespace App\Enums;

/**
 * Statut de rapprochement d'UNE transaction (colonne normalized_transactions.matching_status).
 *
 * Toute transaction importée commence en Unmatched. Le moteur de
 * rapprochement (RuleMatcher) la passe ensuite en Matched, Partial ou
 * Conflict ; un utilisateur peut aussi la marquer Ignored.
 * (Voir ExceptionStatus pour l'explication des enums.)
 */
enum MatchingStatus: string
{
    case Unmatched = 'unmatched';
    case Matched = 'matched';
    case Partial = 'partial';
    case Conflict = 'conflict';
    case Ignored = 'ignored';

    /** Libellé français affiché dans l'interface. */
    public function label(): string
    {
        return match ($this) {
            self::Unmatched => 'Non rapproché',
            self::Matched => 'Rapproché',
            self::Partial => 'Partiel',
            self::Conflict => 'Conflit',
            self::Ignored => 'Ignoré',
        };
    }

    /** Classe CSS Bootstrap du badge coloré. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Unmatched => 'bg-secondary',
            self::Matched => 'bg-success',
            self::Partial => 'bg-warning text-dark',
            self::Conflict => 'bg-danger',
            self::Ignored => 'bg-light text-dark',
        };
    }
}
