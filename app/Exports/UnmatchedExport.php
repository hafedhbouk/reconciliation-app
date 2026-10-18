<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Fichier d'export des écarts entre deux fichiers (CSV, Excel ou PDF).
 *
 * Particularités :
 *   - StringValueBinder : toutes les cellules sont écrites en TEXTE. Ainsi
 *     Excel ne transforme pas une référence « 000123 » en nombre 123 ;
 *   - CSV au format attendu par Excel en français : séparateur « ; » et BOM
 *     (marqueur qui signale l'encodage UTF-8, pour que les accents s'affichent correctement) ;
 *   - mise en page PDF : A4 paysage, colonnes de largeur fixe, petite police.
 * Les lignes sont préparées par GenerateMatchingExportJob::handleUnmatched().
 */
class UnmatchedExport extends StringValueBinder implements FromArray, WithCustomCsvSettings, WithCustomValueBinder, WithStyles
{
    /** @param  array  $rows  première ligne = titres, puis une ligne par écart */
    public function __construct(private array $rows) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';',
            'enclosure' => '"',
            'line_ending' => "\r\n",
            'use_bom' => true,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4);
        $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()->setWrapText(true);
        foreach (['A' => 8, 'B' => 24, 'C' => 24, 'D' => 20, 'E' => 12, 'F' => 22, 'G' => 18, 'H' => 14] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getStyle($sheet->calculateWorksheetDimension())->getFont()->setSize(9);

        // Ligne 1 (les titres) en gras.
        return [1 => ['font' => ['bold' => true]]];
    }
}
