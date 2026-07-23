<?php

namespace App\Contracts;

interface TransformPrimitive
{
    public static function key(): string;

    /**
     * @param array<string,mixed> $config this step's config (e.g. ['chars' => ['B', 'b']])
     * @param array<string,mixed> $rawRow the full raw row, for primitives needing cross-column context
     */
    public function apply(mixed $value, array $config, array $rawRow): mixed;
}
