<?php

namespace App\Enums;

enum TransformType: string
{
    case Trim = 'trim';
    case StripPrefixChars = 'strip_prefix_chars';
    case FixedWidthMillimes = 'fixed_width_millimes';
    case DecimalStringToMillimes = 'decimal_string_to_millimes';
    case DateParse = 'date_parse';
    case SubstringAfterNthDelimiter = 'substring_after_nth_delimiter';
    case ZeroPad = 'zero_pad';

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
        };
    }
}
