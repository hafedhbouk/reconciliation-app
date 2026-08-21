<?php

namespace App\Enums;

enum MappingTargetField: string
{
    case Reference = 'reference';
    case NumAutorisation = 'num_autorisation';
    case Amount = 'amount';
    case Date = 'date';
    case Datetime = 'datetime';
    case Canal = 'canal';
    case CurrencyCode = 'currency_code';
    case StatusRaw = 'status_raw';
    case SecondaryReference = 'secondary_reference';
    case Session = 'session';

    public function label(): string
    {
        return match ($this) {
            self::Reference => 'Référence (clé de rapprochement)',
            self::NumAutorisation => 'N° autorisation',
            self::Amount => 'Montant',
            self::Date => 'Date',
            self::Datetime => 'Date et heure',
            self::Canal => 'Canal',
            self::CurrencyCode => 'Devise',
            self::StatusRaw => 'Statut / Type (brut)',
            self::SecondaryReference => 'Référence secondaire',
            self::Session => 'Session',
        };
    }

    /**
     * Core fields map onto a real transactions column; auxiliary fields are
     * only captured inside transformed_data/raw_payload for traceability.
     * num_autorisation has no dedicated column on transactions — it lives in
     * raw_payload (the matching layer resolves it from there), same as
     * secondary_reference, status_raw, and session.
     */
    public function isCore(): bool
    {
        return match ($this) {
            self::Reference, self::Amount, self::Date, self::Datetime, self::Canal, self::CurrencyCode => true,
            self::NumAutorisation, self::StatusRaw, self::SecondaryReference, self::Session => false,
        };
    }
}
