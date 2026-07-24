<?php

namespace App\Services\Import\Transforms;

use App\Contracts\TransformPrimitive;
use App\Enums\TransformType;

class SubstringFromRightTransform implements TransformPrimitive
{
    public static function key(): string
    {
        return TransformType::SubstringFromRight->value;
    }

    /**
     * Takes the last N characters of the value, regardless of any
     * delimiters inside it. Built for WEB(STEG)'s fused "session,référence"
     * column, where the reference is always the rightmost 9 digits and the
     * session prefix's own length isn't reliable enough to split on.
     * config: length.
     */
    public function apply(mixed $value, array $config, array $rawRow): mixed
    {
        $value = (string) $value;
        $length = $config['length'] ?? null;

        return $length !== null ? mb_substr($value, -$length) : $value;
    }
}
