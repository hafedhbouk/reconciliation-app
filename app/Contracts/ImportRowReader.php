<?php

namespace App\Contracts;

/**
 * Contrat pour les lecteurs de fichiers importés.
 *
 * Tout lecteur (CSV, XLSX) doit exposer :
 *  - headers() : la liste brute des en-têtes du fichier
 *  - read() : un générateur de lignes numérotées (1-indexé, données
 *    uniquement) associant chaque colonne à son en-tête
 */
interface ImportRowReader
{
    /** @return array<int,string> en-têtes bruts, dans l'ordre du fichier */
    public function headers(string $absolutePath, array $sourceConfig): array;

    /** @return \Generator<int,array<string,mixed>> numéro_ligne => ligne brute */
    public function read(string $absolutePath, array $sourceConfig): \Generator;
}
