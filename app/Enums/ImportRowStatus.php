<?php

namespace App\Enums;

/**
 * État d'une ligne de fichier importé (table import_rows).
 *
 * En pratique, ProcessImportJob écrit directement Imported (ligne valide)
 * ou Error (ligne rejetée, avec un message d'erreur). Les états
 * intermédiaires décrivent les étapes du traitement d'une ligne :
 * brute -> transformée -> normalisée -> importée.
 * (Voir ExceptionStatus pour l'explication des enums.)
 */
enum ImportRowStatus: string
{
    case Pending = 'pending';
    case Transformed = 'transformed';
    case Normalized = 'normalized';
    case Imported = 'imported';
    case Error = 'error';

    /** Libellé français affiché dans l'interface. */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Transformed => 'Transformé',
            self::Normalized => 'Normalisé',
            self::Imported => 'Importé',
            self::Error => 'Erreur',
        };
    }
}
