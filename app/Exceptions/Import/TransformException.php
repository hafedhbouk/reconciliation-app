<?php

namespace App\Exceptions\Import;

use RuntimeException;

/**
 * Erreur levée par une transformation élémentaire (dossier Transforms) quand
 * la valeur reçue est invalide. MappingEngine la convertit ensuite en
 * RowTransformException, en ajoutant le nom du champ concerné.
 */
class TransformException extends RuntimeException {}
