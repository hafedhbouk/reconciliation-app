<?php

namespace App\Services\Import\Readers;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Restricts PhpSpreadsheet's reader to a single row range so XlsxRowReader
 * never holds more than one window's worth of Cell objects in memory.
 *
 * PhpSpreadsheet appelle readCell() pour chaque cellule du fichier : si la
 * méthode renvoie false, la cellule n'est pas chargée. On ne garde donc que
 * les lignes comprises entre $startRow et $endRow.
 * `final` : cette classe ne peut pas servir de classe parente.
 */
final class RowRangeReadFilter implements IReadFilter
{
    /** `private readonly` : propriétés créées par le constructeur, lisibles seulement dans cette classe et non modifiables. */
    public function __construct(private readonly int $startRow, private readonly int $endRow) {}

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
