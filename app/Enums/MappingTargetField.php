<?php

namespace App\Enums;

enum MappingTargetField: string
{
    case Reference = 'reference';
    case Amount = 'amount';
    case Date = 'date';
    case Datetime = 'datetime';
    case Canal = 'canal';
    case CurrencyCode = 'currency_code';
    case StatusRaw = 'status_raw';
    case SecondaryReference = 'secondary_reference';

    public function label(): string
    {
        return match ($this) {
            self::Reference => 'Référence (clé de rapprochement)',
            self::Amount => 'Montant',
            self::Date => 'Date',
            self::Datetime => 'Date et heure',
            self::Canal => 'Canal',
            self::CurrencyCode => 'Devise',
            self::StatusRaw => 'Statut / Type (brut)',
            self::SecondaryReference => 'Référence secondaire',
        };
    }

    /**
     * Core fields map onto a real transactions column; auxiliary fields are
     * only captured inside transformed_data/raw_payload for traceability.
     */
    public function isCore(): bool
    {
        return match ($this) {
            self::Reference, self::Amount, self::Date, self::Datetime, self::Canal, self::CurrencyCode => true,
            self::StatusRaw, self::SecondaryReference => false,
        };
    }
}
