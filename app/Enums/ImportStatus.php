<?php

namespace App\Enums;

/**
 * État global d'un import de fichier (table imports).
 *
 * Cycle de vie : Pending (fichier déposé, job en file d'attente)
 *   -> Processing (ProcessImportJob lit le fichier)
 *   -> Completed (toutes les lignes sont valides)
 *      ou PartiallyCompleted (certaines lignes sont en erreur)
 *      ou Failed (le fichier entier est inutilisable, ex. colonnes manquantes).
 * (Voir ExceptionStatus pour l'explication des enums.)
 */
enum ImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case PartiallyCompleted = 'partially_completed';

    /** Libellé français affiché dans l'interface. */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Processing => 'En cours',
            self::Completed => 'Terminé',
            self::Failed => 'Échoué',
            self::PartiallyCompleted => 'Partiellement terminé',
        };
    }

    /** Classe CSS Bootstrap du badge coloré. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-secondary',
            self::Processing => 'bg-info text-dark',
            self::Completed => 'bg-success',
            self::Failed => 'bg-danger',
            self::PartiallyCompleted => 'bg-warning text-dark',
        };
    }
}
