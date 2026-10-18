<?php

namespace App\Enums;

/**
 * Statut de traitement d'une exception (anomalie de rapprochement).
 *
 * Pour un débutant : un « enum » est une liste fermée de valeurs possibles.
 * `: string` signifie que chaque cas a une valeur texte, celle qui est
 * stockée en base (ex. 'in_review'). Dans le code, on écrit
 * ExceptionStatus::InReview plutôt que la chaîne 'in_review' : une faute de
 * frappe devient alors une erreur détectée immédiatement.
 *
 * Cycle de vie habituel : Open -> InReview -> Resolved (ou Ignored).
 */
enum ExceptionStatus: string
{
    case Open = 'open';
    case InReview = 'in_review';
    case Resolved = 'resolved';
    case Ignored = 'ignored';

    /**
     * Libellé français affiché dans l'interface.
     * `match` est comme un `switch`, mais il renvoie une valeur et
     * échoue si un cas est oublié.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Ouvert',
            self::InReview => 'En cours de traitement',
            self::Resolved => 'Résolu',
            self::Ignored => 'Ignoré',
        };
    }

    /** Classe CSS Bootstrap du badge coloré affiché dans les listes. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Open => 'bg-danger',
            self::InReview => 'bg-warning text-dark',
            self::Resolved => 'bg-success',
            self::Ignored => 'bg-secondary',
        };
    }
}
