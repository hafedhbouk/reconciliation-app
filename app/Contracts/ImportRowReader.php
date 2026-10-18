<?php

namespace App\Contracts;

/**
 * Contrat pour les lecteurs de fichiers importés.
 *
 * Pour un débutant : une « interface » est une liste de méthodes qu'une
 * classe s'engage à fournir, sans dire comment. Ici, CsvRowReader et
 * XlsxRowReader (dans app/Services/Import/Readers) implémentent ce contrat.
 * Le reste de l'application (ProcessImportJob) ne sait donc pas si le
 * fichier est un CSV ou un Excel : il appelle simplement headers() et read().
 *
 * Tout lecteur (CSV, XLSX) doit exposer :
 *  - headers() : la liste brute des en-têtes du fichier
 *  - read() : un générateur de lignes numérotées (1-indexé, données
 *    uniquement) associant chaque colonne à son en-tête
 *
 * « Générateur » (\Generator) : au lieu de charger les 80 000 lignes en
 * mémoire d'un coup, le lecteur les renvoie une par une avec `yield`.
 */
interface ImportRowReader
{
    /**
     * Lit uniquement la ligne d'en-tête (noms des colonnes).
     *
     * @param  string  $absolutePath  chemin complet du fichier sur le disque
     * @param  array  $sourceConfig  réglages de la source (ex. délimiteur CSV)
     * @return array<int,string> en-têtes bruts, dans l'ordre du fichier
     */
    public function headers(string $absolutePath, array $sourceConfig): array;

    /**
     * Parcourt les lignes de données (sans l'en-tête).
     *
     * Exemple de valeur produite : 2 => ['Date' => '15/05/2026', 'Montant (TND)' => '253.000', ...]
     *
     * @return \Generator<int,array<string,mixed>> numéro_ligne => ligne brute
     */
    public function read(string $absolutePath, array $sourceConfig): \Generator;
}
