<?php

namespace App\Services\Import\Transforms;

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
