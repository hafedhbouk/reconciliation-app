<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Levée quand on demande l'export des écarts (lignes non rapprochées) d'une
 * comparaison qui n'est pas prête : calcul pas terminé, en échec, ou trop de
 * lignes pour le format demandé. Le contrôleur l'attrape pour afficher un
 * message à l'utilisateur au lieu d'une page d'erreur.
 */
class UnmatchedExportUnavailableException extends RuntimeException
{
}
