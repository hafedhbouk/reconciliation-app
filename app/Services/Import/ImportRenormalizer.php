<?php

namespace App\Services\Import;

use App\Models\Import;
use App\Services\Matching\SnapshotRows;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Prepare a compressed, reversible plan before updating existing imported rows.
 *
 * « Renormaliser » = recalculer les données transformées et normalisées
 * d'imports DÉJÀ enregistrés, par exemple après une correction du mapping
 * ou du TransactionNormalizer, sans réimporter les fichiers.
 *
 * Par sécurité, l'opération se fait en deux temps :
 *   1. prepare() : calcule les nouvelles valeurs et écrit un « plan » dans un
 *      fichier compressé (.gz). Chaque ligne du plan contient l'état AVANT
 *      et les modifications à appliquer. Aucune donnée n'est modifiée.
 *   2. apply()   : relit le plan et applique les modifications dans une seule
 *      transaction SQL. Si une ligne a changé entre-temps, tout est annulé.
 * Le plan sert aussi de sauvegarde : l'état AVANT permet de revenir en arrière.
 * Utilisé par le script scripts/renormalize-alpha-bna.php.
 */
class ImportRenormalizer
{
    public function __construct(private MappingEngine $engine, private TransactionNormalizer $normalizer) {}

    /**
     * Étape 1 : construit le plan dans le fichier $path (qui ne doit pas déjà exister).
     *
     * @param  array<int>  $importIds  imports à renormaliser
     * @param  callable|null  $progress  fonction appelée avec un message de progression (facultative)
     * @return array résumé par import (nom du fichier, nombre de lignes, version du mapping)
     */
    public function prepare(array $importIds, string $path, ?callable $progress = null): array
    {
        if (file_exists($path)) {
            throw new RuntimeException('Le fichier de sauvegarde existe déjà.');
        }
        // Fichier compressé en écriture ('w'), binaire ('b'), compression niveau 6.
        $stream = gzopen($path, 'wb6');
        if ($stream === false) {
            throw new RuntimeException('Impossible de créer la sauvegarde.');
        }
        $summary = [];
        $total = 0;
        try {
            foreach ($importIds as $importId) {
                $import = Import::with('source')->findOrFail($importId);
                if (! in_array($import->source->code, ['ALPHA', 'BNA', 'WEB', 'STEG', 'SMT'], true)) {
                    throw new RuntimeException('Source non prise en charge pour la renormalisation.');
                }
                $versions = app(ImportMappingVersion::class);
                $mappingSnapshot = $versions->capture($import->source);
                $mappings = $versions->mappings($mappingSnapshot);
                $count = 0;
                DB::table('transactions')->where('import_id', $importId)->whereNull('deleted_at')->orderBy('id')
                    ->chunkById(250, function ($transactions) use ($import, $mappings, $stream, &$count, &$total, $progress) {
                        $rawRows = DB::table('import_rows')->whereIn('id', $transactions->pluck('import_row_id'))->get()->keyBy('id');
                        $normalized = DB::table('normalized_transactions')->whereIn('transaction_id', $transactions->pluck('id'))->get()->keyBy('transaction_id');
                        // Pour chaque transaction : on relit la ligne brute du fichier et on refait
                        // tout le calcul, comme lors de l'import (MappingEngine puis TransactionNormalizer).
                        foreach ($transactions as $transaction) {
                            $row = $rawRows->get($transaction->import_row_id);
                            $nt = $normalized->get($transaction->id);
                            if (! $row || ! $nt || $row->import_id !== $import->id || $row->deleted_at !== null || $nt->deleted_at !== null) {
                                throw new RuntimeException("Liens incomplets pour la transaction {$transaction->id}.");
                            }
                            $raw = json_decode($row->raw_data, true, 512, JSON_THROW_ON_ERROR);
                            $mapped = $this->engine->transformRow($raw, $mappings);
                            if (in_array($import->source->code, ['ALPHA', 'BNA'], true) && trim((string) ($mapped['num_autorisation'] ?? '')) === '') {
                                throw new RuntimeException("Autorisation absente pour la transaction {$transaction->id}.");
                            }
                            $built = $this->normalizer->buildTransactionRow($mapped, $import->source, $import, $import->imported_by);
                            $snapshot = $this->normalizer->computeNormalizedSnapshot($built);
                            // Renormalization is not a new matching run.
                            $snapshot['matching_status'] = $nt->matching_status;
                            $transactionPatch = array_intersect_key($built, array_flip([
                                'external_reference', 'transaction_date', 'transaction_datetime', 'amount_millimes', 'canal', 'raw_payload',
                            ]));
                            $normalizedPatch = $snapshot;
                            unset($normalizedPatch['matching_status']);
                            $rowPatch = [
                                'transformed_data' => json_encode($mapped, JSON_THROW_ON_ERROR),
                                'normalized_data' => json_encode($this->normalizer->importRowNormalizedData($snapshot), JSON_THROW_ON_ERROR),
                            ];
                            // Une ligne du plan = l'état avant + le « patch » (colonnes modifiées) pour les 3 tables.
                            $this->write($stream, ['rows' => [
                                'transactions' => ['before' => (array) $transaction, 'patch' => $transactionPatch],
                                'normalized_transactions' => ['before' => (array) $nt, 'patch' => $normalizedPatch],
                                'import_rows' => ['before' => (array) $row, 'patch' => $rowPatch],
                            ]]);
                            $count++;
                            $total++;
                        }
                        if ($count % 5000 === 0) {
                            $progress && $progress("Import {$import->id} : {$count} lignes vérifiées");
                        }
                    });
                $summary[$importId] = ['file' => $import->original_filename, 'rows' => $count, 'mapping_snapshot' => $mappingSnapshot, 'mapping_hash' => $versions->hash($mappingSnapshot)];
                $progress && $progress("Import {$importId} prêt : {$count} lignes");
            }
            // Back up and invalidate file-difference caches that depend on these imports.
            foreach (DB::table('unmatched_snapshots')->whereIn('import_a_id', $importIds)->orWhereIn('import_b_id', $importIds)->get() as $snapshot) {
                $this->write($stream, ['rows' => ['unmatched_snapshots' => ['before' => (array) $snapshot, 'patch' => [
                    'status' => 'failed', 'result_a' => null, 'result_b' => null,
                    'error' => 'Données renormalisées : relancer la comparaison.',
                ]]]]);
            }
            // Dernière ligne du plan (« pied ») : prouve que la préparation est allée jusqu'au bout.
            $this->write($stream, ['complete' => true, 'transactions' => $total, 'imports' => $summary]);
        } finally {
            gzclose($stream);
        }

        return $summary;
    }

    /**
     * Étape 2 : applique un plan préparé par prepare(). Renvoie le nombre de transactions mises à jour.
     */
    public function apply(string $path, ?callable $progress = null): int
    {
        // An interrupted or invalid preparation must never be applied.
        $footer = null;
        $planned = 0;
        foreach ($this->records($path) as $record) {
            $footer = $record;
            $planned += isset($record['rows']['transactions']) ? 1 : 0;
        }
        if (($footer['complete'] ?? false) !== true || $footer['transactions'] !== $planned) {
            throw new RuntimeException('Plan incomplet : aucune modification effectuée.');
        }

        return DB::transaction(function () use ($path, $progress, $footer) {
            $importIds = array_keys($footer['imports']);
            $imports = Import::whereIn('id', $importIds)->orderBy('id')->lockForUpdate()->get();
            if ($imports->count() !== count($importIds) || $imports->contains(fn ($import) => in_array($import->status->value, ['pending', 'processing'], true))) {
                throw new RuntimeException('Un import est supprimé ou encore en cours de traitement.');
            }
            $batch = [];
            $count = 0;
            foreach ($this->records($path) as $record) {
                if (! isset($record['rows'])) {
                    continue;
                }
                $batch[] = $record['rows'];
                $count += isset($record['rows']['transactions']) ? 1 : 0;
                if (count($batch) === 250) {
                    $this->applyBatch($batch);
                    $batch = [];
                    if ($count % 5000 === 0) {
                        $progress && $progress("{$count} transactions mises à jour (validation finale en attente)");
                    }
                }
            }
            if ($batch !== []) {
                $this->applyBatch($batch);
            }

            foreach ($imports as $import) {
                $version = $footer['imports'][$import->id];
                if (isset($version['mapping_snapshot'], $version['mapping_hash'])) {
                    $import->update(['mapping_snapshot' => $version['mapping_snapshot'], 'mapping_hash' => $version['mapping_hash']]);
                }
            }
            app(SnapshotRows::class)->invalidate($importIds);

            return $count;
        });
    }

    /**
     * Applique un paquet de 250 lignes du plan, table par table.
     * Pour chaque ligne, on vérifie qu'elle est TOUJOURS identique à l'état
     * « avant » du plan ; sinon quelqu'un l'a modifiée entre-temps et on annule tout.
     * upsert() = « mets à jour si la ligne existe » en une seule requête.
     */
    private function applyBatch(array $batch): void
    {
        foreach (['transactions', 'normalized_transactions', 'import_rows', 'unmatched_snapshots'] as $table) {
            $entries = array_values(array_filter(array_column($batch, $table)));
            if ($entries === []) {
                continue;
            }
            $current = DB::table($table)->whereIn('id', array_column(array_column($entries, 'before'), 'id'))->lockForUpdate()->get()->keyBy('id');
            $updates = [];
            foreach ($entries as $entry) {
                $before = $entry['before'];
                if (! $current->has($before['id']) || (array) $current[$before['id']] != $before) {
                    throw new RuntimeException("{$table} #{$before['id']} a changé depuis la sauvegarde : opération annulée.");
                }
                $updates[] = array_replace($before, $entry['patch'], ['updated_at' => now()->toDateTimeString()]);
            }
            DB::table($table)->upsert($updates, ['id'], [...array_keys($entries[0]['patch']), 'updated_at']);
        }
    }

    /** Écrit une ligne JSON dans le plan, et vérifie que tout a bien été écrit (disque plein, etc.). */
    private function write($stream, array $record): void
    {
        $line = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n";
        if (gzwrite($stream, $line) !== strlen($line)) {
            throw new RuntimeException('Échec de sauvegarde : écriture incomplète.');
        }
    }

    /** Relit le plan ligne par ligne (générateur : une seule ligne en mémoire à la fois). */
    private function records(string $path): \Generator
    {
        $stream = gzopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Sauvegarde illisible.');
        }
        try {
            while (($line = gzgets($stream)) !== false) {
                yield json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            }
        } finally {
            gzclose($stream);
        }
    }
}
