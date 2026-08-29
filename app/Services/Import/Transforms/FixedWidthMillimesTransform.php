<?php

namespace App\Services\Import\Transforms;

/**
 * Transforme FixedWidthMillimes : convertit une chaîne à largeur fixe
 * (ex: " 000000042000") en entier millimes.
 *
 * Utilisé pour les sources où le montant est déjà exprimé en millimes
 * dans une colonne textuelle à padding espaces. Valide que la valeur
 * ne contient que des chiffres et un éventuel signe moins.
 */
use App\Contracts\TransformPrimitive;
use App\Enums\TransformType;
use App\Exceptions\Import\TransformException;

class FixedWidthMillimesTransform implements TransformPrimitive
{
    public static function key(): string
    {
        return TransformType::FixedWidthMillimes->value;
    }

    /**
     * For values that are already expressed in millimes (fixed-width,
     * space-padded strings like ' 000000042000') — just trim and cast.
     */
    public function apply(mixed $value, array $config, array $rawRow): mixed
    {
        $trimmed = trim((string) $value);

        if (! preg_match('/^-?\d+$/', $trimmed)) {
            throw new TransformException("Valeur non numérique pour un montant déjà en millimes : '{$value}'");
        }

        return (int) $trimmed;
    }
}
