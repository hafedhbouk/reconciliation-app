<?php

namespace App\Services\Import\Transforms;

/**
 * Transforme ZeroPad : complète une référence numérique avec des zéros
 * en tête pour atteindre une largeur fixe.
 *
 * Indispensable pour les sources Excel (Alpha, BNA) où les cellules
 * numériques perdent leurs zéros initiaux lors de la lecture.
 */
use App\Contracts\TransformPrimitive;
use App\Enums\TransformType;

class ZeroPadTransform implements TransformPrimitive
{
    public static function key(): string
    {
        return TransformType::ZeroPad->value;
    }

    /**
     * Pads a numeric reference back up to its fixed real-world width with
     * leading zeros. Needed for xlsx sources (Alpha, BNA): a reference
     * column stored as a numeric Excel cell silently loses its leading
     * zeros on read, so "003512" comes back as "3512". config: length
     * (target width).
     */
    public function apply(mixed $value, array $config, array $rawRow): mixed
    {
        $value = (string) $value;
        $length = $config['length'] ?? null;

        if ($length === null || mb_strlen($value) >= $length) {
            return $value;
        }

        return str_pad($value, $length, '0', STR_PAD_LEFT);
    }
}
