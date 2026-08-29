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
 */
use App\Exceptions\Import\MissingRequiredFieldException;
use App\Exceptions\Import\RowTransformException;
use App\Exceptions\Import\TransformException;
use App\Models\SourceColumnMapping;
use Illuminate\Support\Collection;

class MappingEngine
{
    public function __construct(private TransformRegistry $registry)
    {
    }

    /**
     * @param array<string,mixed> $rawRow
     * @param Collection<int,SourceColumnMapping> $mappings
     * @return array<string,mixed> keyed by MappingTargetField value
     *
     * @throws MissingRequiredFieldException
     * @throws RowTransformException
     */
     public function transformRow(array $rawRow, Collection $mappings): array
     {
         $out = [];

         foreach ($mappings as $mapping) {
             $value = $rawRow[$mapping->source_column] ?? null;

             // Si la colonne source est absente ou vide, on respecte la
             // contrainte "obligatoire" avant de passer aux transforms.
             if ($value === null || $value === '') {
                 if ($mapping->is_required) {
                     throw new MissingRequiredFieldException($mapping->target_field, $mapping->source_column);
                 }

                 $out[$mapping->target_field] = null;

                 continue;
             }

             // Appliquer chaque transform dans l'ordre défini par
             // sort_order (ex: Trim -> StripPrefix -> ZeroPad).
             foreach ((array) $mapping->transform as $step) {
                 try {
                     $value = $this->registry->make($step['key'])->apply($value, $step['config'] ?? [], $rawRow);
                 } catch (TransformException $e) {
                     throw new RowTransformException($mapping->target_field, $e->getMessage(), previous: $e);
                 }
             }

             $out[$mapping->target_field] = $value;
         }

         return $out;
     }

    /**
     * @param array<int,string> $fileHeaders
     * @param Collection<int,SourceColumnMapping> $requiredMappings
     * @return array<int,string> missing required source_column values, empty = OK
     */
    public function validateHeaders(array $fileHeaders, Collection $requiredMappings): array
    {
        return $requiredMappings
            ->pluck('source_column')
            ->reject(fn (string $column) => in_array($column, $fileHeaders, true))
            ->values()
            ->all();
    }
}
