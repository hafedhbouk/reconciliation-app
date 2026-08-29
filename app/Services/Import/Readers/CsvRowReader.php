<?php

namespace App\Services\Import\Readers;

/**
 * Lecteur de fichiers CSV via générateur PHP natif.
 *
 * Utilise fgetcsv en mode streaming pour traiter des fichiers volumineux
 * sans charger l'intégralité en mémoire. Les lignes déséquilibrées
 * (nombre de colonnes ≠ en-têtes) sont complétées ou tronquées plutôt que
 * de provoquer une erreur fatale.
 */
use App\Contracts\ImportRowReader;
use RuntimeException;

class CsvRowReader implements ImportRowReader
{
    public function headers(string $absolutePath, array $sourceConfig): array
    {
        [$delimiter, $enclosure] = $this->delimiterAndEnclosure($sourceConfig);

        $handle = $this->openFile($absolutePath);

        try {
            $headers = fgetcsv($handle, 0, $delimiter, $enclosure);

            return $headers === false ? [] : $headers;
        } finally {
            fclose($handle);
        }
    }

    public function read(string $absolutePath, array $sourceConfig): \Generator
    {
        [$delimiter, $enclosure] = $this->delimiterAndEnclosure($sourceConfig);

        $handle = $this->openFile($absolutePath);

        try {
            $headers = fgetcsv($handle, 0, $delimiter, $enclosure);

            if ($headers === false) {
                return;
            }

            $headerCount = count($headers);
            $rowNumber = 0;

            while (($row = fgetcsv($handle, 0, $delimiter, $enclosure)) !== false) {
                $rowNumber++;

                // Protection contre les lignes déséquilibrées : on complète
                // les lignes courtes avec null et on tronque les lignes longues
                // pour éviter les décalages de colonnes.
                $rowCount = count($row);
                if ($rowCount < $headerCount) {
                    $row = array_pad($row, $headerCount, null);
                } elseif ($rowCount > $headerCount) {
                    $row = array_slice($row, 0, $headerCount);
                }

                yield $rowNumber => array_combine($headers, $row);
            }
        } finally {
            fclose($handle);
        }
    }

    /** @return array{0:string,1:string} */
    private function delimiterAndEnclosure(array $sourceConfig): array
    {
        return [$sourceConfig['csv_delimiter'] ?? ',', $sourceConfig['csv_enclosure'] ?? '"'];
    }

    /** @return resource */
    private function openFile(string $absolutePath)
    {
        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Impossible d'ouvrir le fichier : {$absolutePath}");
        }

        return $handle;
    }
}
