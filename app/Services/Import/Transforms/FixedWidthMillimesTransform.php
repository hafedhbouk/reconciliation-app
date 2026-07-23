<?php

namespace App\Services\Import\Transforms;

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
