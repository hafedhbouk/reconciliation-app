<?php

namespace App\Enums;

enum ExceptionType: string
{
    case Unmatched = 'unmatched';
    case AmountMismatch = 'amount_mismatch';
    case DateMismatch = 'date_mismatch';
    case Duplicate = 'duplicate';
    case Orphan = 'orphan';
    case Conflict = 'conflict';

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
