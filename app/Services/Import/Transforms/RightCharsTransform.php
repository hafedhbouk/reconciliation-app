<?php

namespace App\Services\Import\Transforms;

use App\Contracts\TransformPrimitive;
use App\Enums\TransformType;
use App\Exceptions\Import\TransformException;

class RightCharsTransform implements TransformPrimitive
{
    public static function key(): string
    {
        return TransformType::RightChars->value;
    }

    /**
     * Extracts the N rightmost characters from a value.
     * For WEB/STEG fused session+reference column: the reference is the
     * 9 digits at the right of that column.
     *
     * @param array{length?: int} $config
     */
    public function apply(mixed $value, array $config, array $rawRow): mixed
    {
        $length = $config['length'] ?? throw new TransformException('Le paramètre "length" est requis pour right_chars.');

        $trimmed = trim((string) $value);

        if ($length <= 0) {
            throw new TransformException('Le paramètre "length" doit être positif pour right_chars.');
        }

        if (mb_strlen($trimmed) < $length) {
            throw new TransformException("Impossible d'extraire {$length} caractères à droite de '{$value}' (longueur insuffisante).");
        }

        return mb_substr($trimmed, -$length);
    }
}
