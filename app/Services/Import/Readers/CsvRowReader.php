<?php

namespace App\Services\Import\Readers;

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

                // Guard against ragged rows (rare, but real-world exports aren't always clean):
                // pad short rows with null, truncate long ones, rather than crashing the import.
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
