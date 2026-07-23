<?php

namespace App\Services\Import\Transforms;

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
