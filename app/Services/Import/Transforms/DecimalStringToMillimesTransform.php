<?php

namespace App\Services\Import\Transforms;

/**
 * Transforme DecimalStringToMillimes : convertit une chaîne décimale
 * (ex: "78.000" ou "4.75") en montant en millimes.
 *
 * Utilise de l'arithmétique sur chaînes (jamais de multiplication par
 * un flottant) pour éviter l'accumulation d'erreurs d'arrondi sur des
 * dizaines de milliers de lignes. Gère les séparateurs de milliers
 * (espaces et espaces insécables) rencontrés dans les fichiers BNA.
 */
use App\Contracts\TransformPrimitive;
use App\Enums\TransformType;
use App\Exceptions\Import\TransformException;

class DecimalStringToMillimesTransform implements TransformPrimitive
{
    public static function key(): string
    {
        return TransformType::DecimalStringToMillimes->value;
    }

    /**
     * Converts a decimal string like '78.000' or '4.75' to millimes (78000,
     * 4750) using string arithmetic — never float multiplication, which
     * accumulates rounding error across tens of thousands of rows.
     *
     * Real BNA data includes a space thousands-separator on amounts >= 1000
     * (e.g. '1 773.000') — verified against the live file during manual
     * testing, not assumed — so both regular and non-breaking spaces between
     * digit groups are stripped before parsing.
     */
    public function apply(mixed $value, array $config, array $rawRow): mixed
    {
        $decimals = $config['decimals'] ?? 3;
        $trimmed = preg_replace('/[\s\x{00A0}]+/u', '', trim((string) $value));

        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $trimmed, $matches)) {
            throw new TransformException("Valeur décimale invalide : '{$value}'");
        }

        [, $sign, $integerPart, $fractionalPart] = $matches + [3 => ''];

        $fractionalPart = substr(str_pad($fractionalPart, $decimals, '0'), 0, $decimals);

        return (int) ($sign.$integerPart.$fractionalPart);
    }
}
