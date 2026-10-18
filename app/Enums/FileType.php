<?php

namespace App\Enums;

/**
 * Formats de fichier acceptés pour une source.
 * ImportRowReaderFactory choisit le bon lecteur (CSV ou Excel) selon cette valeur.
 * (Voir ExceptionStatus pour l'explication des enums.)
 */
enum FileType: string
{
    case Csv = 'csv';   // texte séparé par un délimiteur (WEB, SMT)
    case Xls = 'xls';   // ancien format Excel
    case Xlsx = 'xlsx'; // format Excel moderne (ALPHA, BNA)
}
