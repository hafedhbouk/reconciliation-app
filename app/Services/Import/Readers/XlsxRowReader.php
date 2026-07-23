<?php

namespace App\Services\Import\Readers;

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
     */
    private const READER_WINDOW = 1000;

    public function headers(string $absolutePath, array $sourceConfig): array
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new RowRangeReadFilter(1, 1));

        $spreadsheet = $reader->load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [];
        foreach ($sheet->getRowIterator(1, 1) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $headers[] = (string) $cell->getFormattedValue();
            }
        }

        $spreadsheet->disconnectWorksheets();

        return $headers;
    }

    public function read(string $absolutePath, array $sourceConfig): \Generator
    {
        $headers = $this->headers($absolutePath, $sourceConfig);
        $headerCount = count($headers);
        $totalRows = $this->totalRows($absolutePath);

        if ($totalRows < 2) {
            return;
        }

        $rowNumber = 0;
        $windowStart = 2; // row 1 is the header row

        while ($windowStart <= $totalRows) {
            $windowEnd = min($windowStart + self::READER_WINDOW - 1, $totalRows);

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

                $cellCount = count($cells);
                if ($cellCount < $headerCount) {
                    $cells = array_pad($cells, $headerCount, null);
                } elseif ($cellCount > $headerCount) {
                    $cells = array_slice($cells, 0, $headerCount);
                }

                $rowNumber++;

                yield $rowNumber => array_combine($headers, $cells);
            }

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
     */
    private function totalRows(string $absolutePath): int
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $info = $reader->listWorksheetInfo($absolutePath);

        return (int) ($info[0]['totalRows'] ?? 0);
    }
}
