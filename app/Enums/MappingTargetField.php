<?php

namespace App\Enums;

/**
 * Champs « internes » de l'application vers lesquels on peut associer une colonne de fichier.
 *
 * Chaque source a ses propres noms de colonnes : BNA écrit « N° autorisation »,
 * ALPHA écrit « NUM_AUTO ». Le « mapping » (table source_column_mappings)
 * associe chaque colonne du fichier à un de ces champs internes, pour que
 * la suite du traitement manipule toujours les mêmes noms.
 *
 * Exemple : la colonne BNA « Montant (TND) » est associée à Amount ('amount').
 * (Voir ExceptionStatus pour l'explication des enums.)
 */
enum MappingTargetField: string
{
    case Reference = 'reference';                     // référence principale de paiement (ALPHA, WEB)
    case NumAutorisation = 'num_autorisation';        // N° d'autorisation bancaire (ALPHA, BNA)
    case Amount = 'amount';                           // montant, toujours converti en millimes
    case Date = 'date';
    case Datetime = 'datetime';
    case Canal = 'canal';                             // canal de paiement (ex. WEB)
    case CurrencyCode = 'currency_code';              // code devise ISO (ex. TND)
    case StatusRaw = 'status_raw';                    // type de transaction tel qu'écrit dans le fichier
    case SecondaryReference = 'secondary_reference';  // référence complémentaire (recu_paie pour WEB)
    case Session = 'session';                         // identifiant de session WEB

    /** Libellé français affiché dans l'écran de mapping. */
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
     * Indique si le champ a sa propre colonne dans la table transactions.
     *
     * Core fields map onto a real transactions column; auxiliary fields are
     * only captured inside transformed_data/raw_payload for traceability.
     * num_autorisation has no dedicated column on transactions — it lives in
     * raw_payload, same as secondary_reference, status_raw, and session.
     * TransactionNormalizer la recopie ensuite dans normalized_transactions
     * (normalized_reference pour BNA, normalized_secondary_reference pour ALPHA),
     * et c'est là que le moteur de rapprochement la lit.
     */
    public function isCore(): bool
    {
        return match ($this) {
            self::Reference, self::Amount, self::Date, self::Datetime, self::Canal, self::CurrencyCode => true,
            self::NumAutorisation, self::StatusRaw, self::SecondaryReference, self::Session => false,
        };
    }
}
