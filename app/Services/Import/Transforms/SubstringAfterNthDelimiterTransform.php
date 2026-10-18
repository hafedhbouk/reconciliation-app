<?php

namespace App\Services\Import\Transforms;

/**
 * Transforme SubstringAfterNthDelimiter : extrait le sous-texte situé
 * après le N-ième délimiteur.
 *
 * Utilisé pour les colonnes composites (ex: session,type,référence) où
 * la référence se trouve après le deuxième séparateur. Le délimiteur et
 * N sont configurables ; une troncature optionnelle (length) permet de
 * ne garder qu'une partie du reste.
 */
use App\Contracts\TransformPrimitive;
use App\Enums\TransformType;
use App\Exceptions\Import\TransformException;

class SubstringAfterNthDelimiterTransform implements TransformPrimitive
{
    public static function key(): string
    {
        return TransformType::SubstringAfterNthDelimiter->value;
    }

    /**
     * Generic form of STEG's rule: "take the 9 characters immediately after
     * the 2nd comma". config: delimiter (default ','), n (1-indexed
     * occurrence), length (optional).
     */
    public function apply(mixed $value, array $config, array $rawRow): mixed
    {
        $delimiter = $config['delimiter'] ?? ',';
        $n = $config['n'] ?? 1;
        $length = $config['length'] ?? null;

        // explode découpe le texte : 'a,b,c123' -> ['a', 'b', 'c123'].
        $parts = explode($delimiter, (string) $value);

        if (count($parts) <= $n) {
            throw new TransformException("Impossible de trouver le {$n}-ième séparateur '{$delimiter}' dans '{$value}'.");
        }

        // On garde tout ce qui suit le n-ième séparateur (n = 2 : 'c123'),
        // en recollant les morceaux suivants s'il y a d'autres séparateurs.
        $remainder = implode($delimiter, array_slice($parts, $n));

        return $length !== null ? mb_substr($remainder, 0, $length) : $remainder;
    }
}
