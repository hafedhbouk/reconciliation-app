<?php

namespace App\Exceptions\Import;

use RuntimeException;

/**
 * Levée quand une ligne importée n'a pas de valeur pour une colonne obligatoire.
 *
 * Pour un débutant : une « exception » est une erreur qu'on « lance » (throw)
 * pour interrompre le traitement en cours. Le code appelant peut la
 * « attraper » (catch) pour réagir. Ici, ProcessImportJob l'attrape et marque
 * seulement la ligne concernée en erreur : le reste du fichier continue.
 */
class MissingRequiredFieldException extends RuntimeException
{
    /**
     * @param  string  $targetField  champ interne attendu (ex. 'amount')
     * @param  string  $sourceColumn  nom de la colonne dans le fichier (ex. 'Montant (TND)')
     */
    public function __construct(public readonly string $targetField, public readonly string $sourceColumn)
    {
        // parent::__construct() appelle le constructeur de RuntimeException avec le message d'erreur.
        parent::__construct("Colonne requise manquante : '{$this->sourceColumn}' (champ '{$this->targetField}')");
    }
}
