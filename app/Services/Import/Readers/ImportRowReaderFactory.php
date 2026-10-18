<?php

namespace App\Services\Import\Readers;

/**
 * Fabrique de lecteurs de fichiers importés.
 *
 * Détermine le lecteur approprié selon le file_type de la Source :
 * - csv -> CsvRowReader (streaming via fgetcsv)
 * - xls/xlsx -> XlsxRowReader (lecture fenêtrée pour limiter la mémoire)
 *
 * Une « fabrique » (factory) est une classe dont le rôle est de créer le bon
 * objet selon la situation. Le code appelant écrit simplement
 * $factory->make($source) et reçoit un ImportRowReader, sans se soucier du format.
 */
use App\Contracts\ImportRowReader;
use App\Models\Source;
use InvalidArgumentException;

class ImportRowReaderFactory
{
    public function make(Source $source): ImportRowReader
    {
        // app(Classe::class) demande à Laravel de construire l'objet (le
        // « conteneur de services » fournit lui-même ses dépendances éventuelles).
        return match ($source->file_type) {
            'csv' => app(CsvRowReader::class),
            'xls', 'xlsx' => app(XlsxRowReader::class),
            default => throw new InvalidArgumentException("Type de fichier non pris en charge : {$source->file_type}"),
        };
    }
}
