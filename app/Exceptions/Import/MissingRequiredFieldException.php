<?php

namespace App\Exceptions\Import;

use RuntimeException;

class MissingRequiredFieldException extends RuntimeException
{
    public function __construct(public readonly string $targetField, public readonly string $sourceColumn)
    {
        parent::__construct("Colonne requise manquante : '{$this->sourceColumn}' (champ '{$this->targetField}')");
    }
}
