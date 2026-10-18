<?php

namespace App\Services\Import;

/**
 * Moteur de mapping : applique la chaîne de transforms définie dans
 * SourceColumnMapping à chaque ligne brute du fichier importé.
 *
 * Responsabilités :
 *  - Vérifier la présence des colonnes obligatoires (validateHeaders)
 *  - Transformer chaque valeur via le TransformRegistry (trim, parse date,
 *    conversion millimes, etc.)
 *  - Lever une MissingRequiredFieldException si une colonne requise est vide
 *
 * Exemple complet (ligne BNA) :
 *   Entrée  : ['Date' => '15/05/2026', 'Montant (TND)' => '253.000', 'N° autorisation' => '504190', ...]
 *   Sortie  : ['date' => '2026-05-15', 'amount' => 253000, 'num_autorisation' => '504190', ...]
 * C'est la « Données transformées » affichée sur la page d'un import.
 */
use App\Exceptions\Import\MissingRequiredFieldException;
use App\Exceptions\Import\RowTransformException;
use App\Exceptions\Import\TransformException;
use App\Models\SourceColumnMapping;
use Illuminate\Support\Collection;

class MappingEngine
{
    /**
     * « Injection de dépendance » : Laravel crée automatiquement un
     * TransformRegistry et le passe au constructeur. On ne fait jamais
     * `new TransformRegistry()` nous-mêmes.
     */
    public function __construct(private TransformRegistry $registry) {}

    /**
     * Convertit une ligne brute du fichier en tableau de champs internes.
     *
     * @param  array<string,mixed>  $rawRow  ligne brute : 'nom de colonne' => valeur
     * @param  Collection<int,SourceColumnMapping>  $mappings  les associations colonne -> champ de la source
     * @return array<string,mixed> keyed by MappingTargetField value
     *
     * @throws MissingRequiredFieldException
     * @throws RowTransformException
     */
    public function transformRow(array $rawRow, Collection $mappings): array
    {
        $out = [];

        // Un passage par mapping, c'est-à-dire par champ interne à remplir.
        foreach ($mappings as $mapping) {
            $value = $rawRow[$mapping->source_column] ?? null;

            // Si la colonne source est absente ou vide, on respecte la
            // contrainte "obligatoire" avant de passer aux transforms.
            if ($value === null || $value === '') {
                if ($mapping->is_required) {
                    throw new MissingRequiredFieldException($mapping->target_field, $mapping->source_column);
                }

                $out[$mapping->target_field] = null;

                continue; // passe directement au mapping suivant
            }

            // Appliquer chaque transform dans l'ordre défini par
            // sort_order (ex: Trim -> StripPrefix -> ZeroPad).
            // Chaque étape reçoit le résultat de la précédente.
            foreach ((array) $mapping->transform as $step) {
                try {
                    // $step ressemble à ['key' => 'zero_pad', 'config' => ['length' => 6]].
                    $value = $this->registry->make($step['key'])->apply($value, $step['config'] ?? [], $rawRow);
                } catch (TransformException $e) {
                    // On relance une erreur plus précise, qui indique le champ concerné.
                    throw new RowTransformException($mapping->target_field, $e->getMessage(), previous: $e);
                }
            }

            $out[$mapping->target_field] = $value;
        }

        return $out;
    }

    /**
     * Vérifie, avant de lire tout le fichier, que les colonnes obligatoires existent.
     * Ainsi, un fichier au mauvais format est refusé tout de suite, pas après 80 000 erreurs.
     *
     * @param  array<int,string>  $fileHeaders  en-têtes trouvés dans le fichier
     * @param  Collection<int,SourceColumnMapping>  $requiredMappings
     * @return array<int,string> missing required source_column values, empty = OK
     */
    public function validateHeaders(array $fileHeaders, Collection $requiredMappings): array
    {
        // Chaîne de méthodes sur une Collection Laravel (un tableau enrichi) :
        return $requiredMappings
            ->pluck('source_column')  // garde seulement les noms de colonnes attendus
            ->reject(fn (string $column) => in_array($column, $fileHeaders, true)) // retire ceux présents dans le fichier
            ->values()                // renumérote les clés 0, 1, 2…
            ->all();                  // convertit en tableau PHP simple
    }
}
