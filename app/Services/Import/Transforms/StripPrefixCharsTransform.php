<?php

namespace App\Services\Import\Transforms;

/**
 * Transforme StripPrefixChars : retire le premier caractère s'il fait
 * partie d'une liste donnée.
 *
 * Conçu pour les sources où un préfixe inconstant (ex: "R" pour
 * référence) peut précéder la valeur. Ne retire jamais le caractère
 * s'il n'est pas dans la liste configurée, car les fichiers réels
 * mélangent souvent des valeurs préfixées et non préfixées.
 */
use App\Contracts\TransformPrimitive;
use App\Enums\TransformType;

class StripPrefixCharsTransform implements TransformPrimitive
{
    public static function key(): string
    {
        return TransformType::StripPrefixChars->value;
    }

    /**
     * Removes the first character only if it's literally in config['chars'].
     * Never assumes the prefix is present — real data mixes prefixed and
     * unprefixed values in the same column.
     */
    public function apply(mixed $value, array $config, array $rawRow): mixed
    {
        $value = (string) $value;
        $chars = $config['chars'] ?? [];

        if ($value !== '' && in_array($value[0], $chars, true)) {
            return substr($value, 1);
        }

        return $value;
    }
}
