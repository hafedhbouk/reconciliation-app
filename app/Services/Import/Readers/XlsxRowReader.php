<?php

namespace App\Services\Import\Readers;

/**
 * Lecteur de fichiers Excel (XLSX) optimisé pour la mémoire.
 *
 * PhpSpreadsheet charge l'intégralité du classeur en mémoire par défaut,
 * ce qui est prohibé pour les fichiers de 80k+ lignes. Ce lecteur lit
 * le fichier par fenêtres de 1 000 lignes via un IReadFilter, en
 * réinstanciant le reader à chaque fenêtre et en libérant l'objet
 * Spreadsheet entre chaque lecture.
 *
 * Utilisé pour les sources ALPHA et BNA.
 * En résumé : lignes 2 à 1001, on libère la mémoire, lignes 1002 à 2001, etc.
 */
use App\Contracts\ImportRowReader;
use PhpOffice\PhpSpreadsheet\IOFactory;

class XlsxRowReader implements ImportRowReader
{
    /**
     * PhpSpreadsheet's Reader::load() builds the entire workbook's Cell
     * objects in memory in one pass — for 80k+ row files that's hundreds of
     * MB. To keep this genuinely chunk-safe (not just "chunked" in name), we
     * re-invoke the reader once per row-range window via an IReadFilter,
     * discarding the Spreadsheet object between windows, exactly how
     * maatwebsite/excel's own WithChunkReading works under the hood.
     * (Taille d'une fenêtre : 1 000 lignes.)
     */
    private const READER_WINDOW = 1000;

    /** Lit la ligne 1 de la feuille active (les noms de colonnes). */
    public function headers(string $absolutePath, array $sourceConfig): array
    {
        // IOFactory choisit automatiquement le bon lecteur (XLSX, XLS…) selon le fichier.
        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true); // ignore la mise en forme (couleurs, polices…) : plus rapide
        $reader->setReadFilter(new RowRangeReadFilter(1, 1)); // ne charge que la ligne 1

        $spreadsheet = $reader->load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [];
        foreach ($sheet->getRowIterator(1, 1) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                // getFormattedValue() : la valeur telle qu'Excel l'affiche (dates et nombres formatés).
                $headers[] = (string) $cell->getFormattedValue();
            }
        }

        // Libère la mémoire occupée par le classeur.
        $spreadsheet->disconnectWorksheets();

        return $headers;
    }

    /**
     * Parcourt les lignes de données, fenêtre par fenêtre (voir l'interface
     * ImportRowReader pour l'explication des générateurs).
     */
    public function read(string $absolutePath, array $sourceConfig): \Generator
    {
        $headers = $this->headers($absolutePath, $sourceConfig);
        $headerCount = count($headers);
        $totalRows = $this->totalRows($absolutePath);

        if ($totalRows < 2) {
            return; // seulement l'en-tête, ou fichier vide
        }

        $rowNumber = 0;
        $windowStart = 2; // La ligne 1 est l'en-tête

        while ($windowStart <= $totalRows) {
            // Fin de la fenêtre : 1 000 lignes plus loin, sans dépasser la dernière ligne.
            $windowEnd = min($windowStart + self::READER_WINDOW - 1, $totalRows);

            // Nouveau lecteur à chaque fenêtre, limité aux lignes windowStart..windowEnd.
            $reader = IOFactory::createReaderForFile($absolutePath);
            $reader->setReadDataOnly(true);
            $reader->setReadFilter(new RowRangeReadFilter($windowStart, $windowEnd));

            $spreadsheet = $reader->load($absolutePath);
            $sheet = $spreadsheet->getActiveSheet();

            foreach ($sheet->getRowIterator($windowStart, $windowEnd) as $row) {
                $cells = [];
                foreach ($row->getCellIterator() as $cell) {
                    $cells[] = $cell->getFormattedValue();
                }

                // Même protection que dans CsvRowReader : autant de valeurs que d'en-têtes.
                $cellCount = count($cells);
                if ($cellCount < $headerCount) {
                    $cells = array_pad($cells, $headerCount, null);
                } elseif ($cellCount > $headerCount) {
                    $cells = array_slice($cells, 0, $headerCount);
                }

                $rowNumber++;

                // Renvoie ['nom de colonne' => valeur, ...] pour cette ligne.
                yield $rowNumber => array_combine($headers, $cells);
            }

            // Libérer la mémoire avant la prochaine fenêtre.
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $reader);

            $windowStart = $windowEnd + 1;
        }
    }

    /**
     * Reads the workbook's true row count from its dimension metadata alone
     * (no cell data loaded) — bounding the read() loop by this instead of by
     * "did the last window come back full" avoids a real bug: PhpSpreadsheet's
     * getRowIterator() yields a Row object for every index in the requested
     * range regardless of whether that row actually has data, so a
     * partial-final-window heuristic can under-detect the end of the file and
     * request a window entirely beyond the sheet's real dimensions.
     * (En clair : on lit le nombre total de lignes dans les informations du
     * fichier, sans charger les cellules, pour savoir quand s'arrêter.)
     */
    private function totalRows(string $absolutePath): int
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $info = $reader->listWorksheetInfo($absolutePath);

        return (int) ($info[0]['totalRows'] ?? 0);
    }
}
