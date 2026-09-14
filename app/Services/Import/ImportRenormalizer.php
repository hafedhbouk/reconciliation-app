<?php

namespace App\Services\Import;

use App\Models\Import;
use App\Services\Matching\SnapshotRows;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Prepare a compressed, reversible plan before updating existing imported rows. */
class ImportRenormalizer
{
    public function __construct(private MappingEngine $engine, private TransactionNormalizer $normalizer) {}

    public function prepare(array $importIds, string $path, ?callable $progress = null): array
    {
        if (file_exists($path)) {
            throw new RuntimeException('Le fichier de sauvegarde existe déjà.');
        }
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
                                'normalized_data' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                            ];
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
            $this->write($stream, ['complete' => true, 'transactions' => $total, 'imports' => $summary]);
        } finally {
            gzclose($stream);
        }

        return $summary;
    }

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

    private function write($stream, array $record): void
    {
        $line = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n";
        if (gzwrite($stream, $line) !== strlen($line)) {
            throw new RuntimeException('Échec de sauvegarde : écriture incomplète.');
        }
    }

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
