<?php

namespace App\Enums;

/**
 * Nature d'une exception détectée pendant le rapprochement.
 * (Voir ExceptionStatus pour l'explication des enums.)
 */
enum ExceptionType: string
{
    case Unmatched = 'unmatched';             // transaction sans correspondance dans l'autre source
    case AmountMismatch = 'amount_mismatch';  // même référence et même date, mais montants différents
    case DateMismatch = 'date_mismatch';      // même référence et même montant, mais dates différentes
    case Duplicate = 'duplicate';             // la même transaction apparaît plusieurs fois dans une source
    case Orphan = 'orphan';                   // paiement sans opération d'origine connue
    case Conflict = 'conflict';               // même référence mais les champs vérifiés ne concordent pas

    /** Libellé français affiché dans l'interface. */
    public function label(): string
    {
        return match ($this) {
            self::Unmatched => 'Non trouvé',
            self::AmountMismatch => 'Montant différent',
            self::DateMismatch => 'Date différente',
            self::Duplicate => 'Doublon',
            self::Orphan => 'Paiement orphelin',
            self::Conflict => 'Conflit',
        };
    }
}
