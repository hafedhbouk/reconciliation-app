<?php

namespace App\Enums;

/**
 * Nombre de transactions attendu de chaque côté d'un rapprochement.
 *
 * Exemple : « 1:N » = une transaction de la source A correspond à plusieurs
 * transactions de la source B (un paiement réglé en plusieurs fois).
 * Les règles fournies utilisent N:M, c'est-à-dire « aucune contrainte » :
 * le moteur accepte n'importe quelle forme de groupe.
 * (Voir ExceptionStatus pour l'explication des enums.)
 */
enum MatchingCardinality: string
{
    case OneToOne = '1:1';
    case OneToMany = '1:N';
    case ManyToOne = 'N:1';
    case ManyToMany = 'N:M';

    /** Libellé français affiché dans l'interface. */
    public function label(): string
    {
        return match ($this) {
            self::OneToOne => 'Un à un',
            self::OneToMany => 'Un à plusieurs',
            self::ManyToOne => 'Plusieurs à un',
            self::ManyToMany => 'Plusieurs à plusieurs',
        };
    }
}
