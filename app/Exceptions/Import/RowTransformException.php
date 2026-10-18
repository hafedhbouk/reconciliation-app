<?php

namespace App\Exceptions\Import;

use RuntimeException;
use Throwable;

/**
 * Levée quand une valeur de ligne ne peut pas être transformée (ex. date illisible).
 *
 * Comme MissingRequiredFieldException, elle est attrapée par
 * ProcessImportJob, qui marque la ligne en erreur sans arrêter l'import.
 */
class RowTransformException extends RuntimeException
{
    /**
     * @param  string  $targetField  champ interne en cours de transformation
     * @param  string  $reason  explication lisible de l'erreur
     * @param  Throwable|null  $previous  l'erreur d'origine, gardée pour le débogage
     */
    public function __construct(public readonly string $targetField, string $reason, ?Throwable $previous = null)
    {
        parent::__construct("{$targetField}: {$reason}", previous: $previous);
    }
}
