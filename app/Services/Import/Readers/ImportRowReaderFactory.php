<?php

namespace App\Services\Import\Readers;

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
