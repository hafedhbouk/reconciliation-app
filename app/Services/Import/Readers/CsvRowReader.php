<?php

namespace App\Services\Import\Readers;

/**
 * Lecteur de fichiers CSV via générateur PHP natif.
 *
 * Utilise fgetcsv en mode streaming pour traiter des fichiers volumineux
 * sans charger l'intégralité en mémoire. Les lignes déséquilibrées
 * (nombre de colonnes ≠ en-têtes) sont complétées ou tronquées plutôt que
 * de provoquer une erreur fatale.
 *
 * Utilisé pour les sources WEB (séparateur « , ») et SMT (séparateur « ; »).
 * Le séparateur est lu dans la configuration de la source (sources.config).
 */
use App\Contracts\ImportRowReader;
use RuntimeException;

class CsvRowReader implements ImportRowReader
{
    /** Lit seulement la première ligne du fichier (les noms de colonnes). */
    public function headers(string $absolutePath, array $sourceConfig): array
    {
        // Déstructuration : la méthode renvoie [séparateur, guillemet] et on range chaque valeur dans sa variable.
        [$delimiter, $enclosure] = $this->delimiterAndEnclosure($sourceConfig);

        $handle = $this->openFile($absolutePath);

        // try/finally : le fichier est refermé dans tous les cas, même si une erreur survient.
        try {
            // fgetcsv lit UNE ligne et la découpe en tableau selon le séparateur.
            $headers = fgetcsv($handle, 0, $delimiter, $enclosure);

            return $headers === false ? [] : $headers;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Parcourt les lignes de données une par une (voir l'interface
     * ImportRowReader pour l'explication des générateurs).
     */
    public function read(string $absolutePath, array $sourceConfig): \Generator
    {
        [$delimiter, $enclosure] = $this->delimiterAndEnclosure($sourceConfig);

        $handle = $this->openFile($absolutePath);

        try {
            // La première ligne contient les en-têtes : on la lit à part.
            $headers = fgetcsv($handle, 0, $delimiter, $enclosure);

            if ($headers === false) {
                return; // fichier vide
            }

            $headerCount = count($headers);
            $rowNumber = 0;

            // fgetcsv renvoie false à la fin du fichier, ce qui arrête la boucle.
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

                // array_combine associe chaque en-tête à sa valeur :
                // ['Date', 'Montant'] + ['15/05/2026', '253.000'] => ['Date' => '15/05/2026', 'Montant' => '253.000']
                // `yield` renvoie cette ligne à l'appelant, puis la boucle reprend ici à la demande suivante.
                yield $rowNumber => array_combine($headers, $row);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Compte les enregistrements comme read() les lit (fgetcsv gère les champs
     * entre guillemets sur plusieurs lignes), sans garder les lignes en mémoire.
     */
    public function countRows(string $absolutePath, array $sourceConfig): ?int
    {
        [$delimiter, $enclosure] = $this->delimiterAndEnclosure($sourceConfig);
        $handle = $this->openFile($absolutePath);

        try {
            if (fgetcsv($handle, 0, $delimiter, $enclosure) === false) {
                return 0; // fichier vide
            }
            $count = 0;
            while (fgetcsv($handle, 0, $delimiter, $enclosure) !== false) {
                $count++;
            }

            return $count;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Séparateur de colonnes (« , » par défaut) et caractère de guillemet (« " » par défaut).
     *
     * @return array{0:string,1:string}
     */
    private function delimiterAndEnclosure(array $sourceConfig): array
    {
        return [$sourceConfig['csv_delimiter'] ?? ',', $sourceConfig['csv_enclosure'] ?? '"'];
    }

    /**
     * Ouvre le fichier en lecture binaire ('rb'), ou lève une erreur claire s'il est introuvable.
     *
     * @return resource
     */
    private function openFile(string $absolutePath)
    {
        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Impossible d'ouvrir le fichier : {$absolutePath}");
        }

        return $handle;
    }
}
