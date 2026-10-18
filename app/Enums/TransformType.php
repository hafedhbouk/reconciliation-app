<?php

namespace App\Enums;

/**
 * Liste des transformations proposées dans l'écran de mapping d'une source.
 *
 * Chaque valeur correspond à la key() d'une classe du dossier
 * app/Services/Import/Transforms (ex. 'zero_pad' -> ZeroPadTransform).
 * (Voir ExceptionStatus pour l'explication des enums.)
 */
enum TransformType: string
{
    case Trim = 'trim';                                         // supprime les espaces au début et à la fin
    case StripPrefixChars = 'strip_prefix_chars';               // ex. « b697464 » -> « 697464 »
    case FixedWidthMillimes = 'fixed_width_millimes';           // ex. « 000000989000 » -> 989000
    case DecimalStringToMillimes = 'decimal_string_to_millimes'; // ex. « 253.000 » (dinars) -> 253000 millimes
    case DateParse = 'date_parse';                              // ex. « 15/05/2026 » -> « 2026-05-15 »
    case SubstringAfterNthDelimiter = 'substring_after_nth_delimiter'; // découpe une valeur composée
    case ZeroPad = 'zero_pad';                                  // ex. « 4190 » -> « 004190 »
    case RightChars = 'right_chars';                            // garde les N derniers caractères

    /** Libellé français affiché dans l'écran de mapping. */
    public function label(): string
    {
        return match ($this) {
            self::Trim => 'Nettoyer (trim)',
            self::StripPrefixChars => 'Supprimer un préfixe conditionnel',
            self::FixedWidthMillimes => 'Montant déjà en millimes (largeur fixe)',
            self::DecimalStringToMillimes => 'Montant décimal vers millimes',
            self::DateParse => 'Analyser une date/heure',
            self::SubstringAfterNthDelimiter => 'Sous-chaîne après le n-ième séparateur',
            self::ZeroPad => 'Compléter avec des zéros à gauche',
            self::RightChars => 'Extraire les N derniers caractères',
        };
    }
}
