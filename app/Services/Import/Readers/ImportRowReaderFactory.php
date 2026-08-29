<?php

namespace App\Services\Import\Readers;

/**
 * Fabrique de lecteurs de fichiers importés.
 *
 * Détermine le lecteur approprié selon le file_type de la Source :
 * - csv -> CsvRowReader (streaming via fgetcsv)
 * - xls/xlsx -> XlsxRowReader (lecture fenêtrée pour limiter la mémoire)
 */
use App\Contracts\ImportRowReader;
use App\Models\Source;
use InvalidArgumentException;

class ImportRowReaderFactory
{
    public function make(Source $source): ImportRowReader
    {
        return match ($source->file_type) {
            'csv' => app(CsvRowReader::class),
            'xls', 'xlsx' => app(XlsxRowReader::class),
            default => throw new InvalidArgumentException("Type de fichier non pris en charge : {$source->file_type}"),
        };
    }
}
