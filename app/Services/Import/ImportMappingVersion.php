<?php

namespace App\Services\Import;

use App\Models\Import;
use App\Models\Source;
use App\Models\SourceColumnMapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * « Fige » la configuration de mapping utilisée par un import.
 *
 * Problème résolu : un administrateur peut modifier le mapping d'une source
 * APRÈS un import. Sans précaution, on ne saurait plus avec quelles règles
 * un ancien fichier a été lu, et un import repris en cours de route
 * mélangerait deux configurations.
 *
 * Solution : au lancement de l'import, on enregistre une copie complète du
 * mapping (mapping_snapshot) et son empreinte (mapping_hash). L'import
 * utilise toujours SA copie. En comparant les empreintes, l'interface
 * affiche « Mapping à vérifier » quand le mapping actuel a changé depuis.
 */
class ImportMappingVersion
{
    /** Photographie la configuration actuelle de la source (réglages + liste des mappings). */
    public function capture(Source $source): array
    {
        return [
            'normalizer_version' => 1,
            'file_type' => $source->file_type,
            'config' => $source->config ?? [],
            'bank_id' => $source->bank_id,
            'default_currency_id' => $source->default_currency_id,
            'mappings' => SourceColumnMapping::where('source_id', $source->id)->orderBy('sort_order')->orderBy('target_field')
                ->get()->map(fn ($mapping) => $mapping->only(['target_field', 'source_column', 'transform', 'is_required', 'sort_order']))->all(),
        ];
    }

    /**
     * Calcule l'empreinte SHA-256 d'une configuration. Deux configurations
     * identiques donnent toujours la même empreinte.
     */
    public function hash(array $snapshot): string
    {
        // JSON object ordering must not create a spurious mapping revision.
        // (On trie les clés de chaque tableau associatif, récursivement, pour
        // que {"a":1,"b":2} et {"b":2,"a":1} aient la même empreinte.)
        $canonical = function ($value) use (&$canonical) {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($canonical, $value);
        };

        return hash('sha256', json_encode($canonical($snapshot), JSON_THROW_ON_ERROR));
    }

    /**
     * Enregistre la copie du mapping sur l'import s'il n'en a pas encore.
     *
     * @param  bool  $refreshUnused  si vrai, remplace la copie d'un import qui n'a encore lu aucune ligne
     */
    public function freeze(Import $import, bool $refreshUnused = false): Import
    {
        // DB::transaction : toutes les requêtes à l'intérieur réussissent ensemble, ou sont toutes annulées.
        return DB::transaction(function () use ($import, $refreshUnused) {
            // lockForUpdate() « verrouille » la ligne jusqu'à la fin de la transaction :
            // deux processus ne peuvent pas figer le même import en même temps.
            $locked = Import::whereKey($import->id)->lockForUpdate()->firstOrFail();
            if ($locked->mapping_snapshot === null || ($refreshUnused && ! $locked->rows()->exists())) {
                if ($locked->rows()->exists()) {
                    // Des lignes ont déjà été lues avec un mapping inconnu : on refuse de deviner.
                    throw new RuntimeException('Ancien import sans version de mapping : renormalisation ou nouvel import nécessaire avant reprise.');
                }
                $snapshot = $this->capture($locked->source);
                $locked->update(['mapping_snapshot' => $snapshot, 'mapping_hash' => $this->hash($snapshot)]);
            }

            return $locked;
        });
    }

    /**
     * Reconstruit des objets SourceColumnMapping (non enregistrés en base) à
     * partir d'une copie figée, pour les donner au MappingEngine.
     */
    public function mappings(array $snapshot): Collection
    {
        return collect($snapshot['mappings'])->map(fn ($row) => new SourceColumnMapping($row));
    }

    /**
     * Compare le mapping figé de l'import au mapping actuel de la source.
     *
     * @return string 'unknown' (ancien import sans copie), 'current' (identique) ou 'outdated' (modifié depuis)
     */
    public function state(Import $import, ?string $currentHash = null): string
    {
        if ($import->mapping_hash === null) {
            return 'unknown';
        }

        // hash_equals compare deux chaînes en temps constant (bonne pratique pour les empreintes).
        return hash_equals($import->mapping_hash, $currentHash ?? $this->hash($this->capture($import->source))) ? 'current' : 'outdated';
    }
}
