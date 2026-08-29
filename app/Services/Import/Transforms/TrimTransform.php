<?php

namespace App\Services\Import\Transforms;

/**
 * Transforme Trim : supprime les espaces blancs en début et fin de chaîne.
 *
 * Transforme les chaînes vides en null après trim, ce qui permet aux
 * mappings non requis de produire une valeur nulle cohérente.
 */
use App\Contracts\TransformPrimitive;
use App\Enums\TransformType;

class TrimTransform implements TransformPrimitive
{
    public static function key(): string
    {
        return TransformType::Trim->value;
    }

    public function apply(mixed $value, array $config, array $rawRow): mixed
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
