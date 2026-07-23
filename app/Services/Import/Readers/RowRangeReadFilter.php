<?php

namespace App\Services\Import\Readers;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Restricts PhpSpreadsheet's reader to a single row range so XlsxRowReader
 * never holds more than one window's worth of Cell objects in memory.
 */
final class RowRangeReadFilter implements IReadFilter
{
    public function __construct(private readonly int $startRow, private readonly int $endRow)
    {
    }

    /**
     * Untyped to match PhpOffice\PhpSpreadsheet\Reader\IReadFilter's own
     * untyped signature — adding types here would narrow the contract and
     * PHP rejects that as an incompatible override.
     */
    public function readCell($columnAddress, $row, $worksheetName = '')
    {
        return $row >= $this->startRow && $row <= $this->endRow;
    }
}
