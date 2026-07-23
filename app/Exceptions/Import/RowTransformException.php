<?php

namespace App\Exceptions\Import;

use RuntimeException;
use Throwable;

class RowTransformException extends RuntimeException
{
    public function __construct(public readonly string $targetField, string $reason, ?Throwable $previous = null)
    {
        parent::__construct("{$targetField}: {$reason}", previous: $previous);
    }
}
