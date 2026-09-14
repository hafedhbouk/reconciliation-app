<?php

namespace App\Jobs;

/**
 * Job asynchrone qui exécute un import complet.
 *
 * Lit le fichier via le lecteur adapté (CSV/XLSX), applique les mappings
 * de colonnes de la Source, insère les transactions et leurs snapshots
 * normalisés par chunks pour rester mémoire-safe sur les gros fichiers.
 *
 * Choix d'architecture : insertions en masse via query builder (pas de
 * Eloquent ::create par ligne) pour éviter des milliers de logs
 * d'audit/événements HasUserstamps pour un seul fichier. Les champs
 * created_by/updated_by sont renseignés manuellement dans les arrays
 * d'insertion.
 */
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Exceptions\Import\MissingRequiredFieldException;
use App\Exceptions\Import\RowTransformException;
use App\Models\Import;
use App\Models\Source;
use App\Models\SourceColumnMapping;
use App\Notifications\ImportProcessedNotification;
use App\Services\Import\ImportMappingVersion;
use App\Services\Import\MappingEngine;
use App\Services\Import\Readers\ImportRowReaderFactory;
use App\Services\Import\TransactionNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Throwable;

/**
 * Reads a Source's file through its saved SourceColumnMapping rows and lands
 * Transaction + NormalizedTransaction rows, chunked to stay memory-safe on
 * 80k+ row files.
 *
 * Trade-off (intentional, not an oversight): rows are written via
 * query-builder bulk insert(), not one Eloquent ::create() per row — so
 * HasUserstamps/Auditable never fire for ImportRow/Transaction/
 * NormalizedTransaction. created_by/updated_by are set manually in the
 * insert arrays instead, and audit-trail granularity is one Import lifecycle
 * entry (the Import model's own ->update() calls below DO go through
 * Eloquent and ARE audited normally), not tens of thousands of audit_logs
 * rows for a single file.
 */
class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 1;

    public int $timeout = 0;

    public function __construct(public int $importId) {}

    public function handle(ImportRowReaderFactory $readerFactory, MappingEngine $engine, TransactionNormalizer $normalizer): void
    {
        $import = Import::query()->with('source')->findOrFail($this->importId);
        if (in_array($import->status, [ImportStatus::Completed, ImportStatus::PartiallyCompleted], true)) {
            return;
        }
        $versions = app(ImportMappingVersion::class);
        $import = $versions->freeze($import);
        $source = clone $import->source;
        $source->file_type = $import->mapping_snapshot['file_type'];
        $source->config = $import->mapping_snapshot['config'];
        $source->bank_id = $import->mapping_snapshot['bank_id'] ?? null;
        $source->default_currency_id = $import->mapping_snapshot['default_currency_id'] ?? null;
        $mappings = $versions->mappings($import->mapping_snapshot);
        $requiredMappings = $mappings->where('is_required', true);

        $reader = $readerFactory->make($source);
        $path = Storage::path($import->stored_path);
        $sourceConfig = $source->config ?? [];
        $fileHash = hash_file('sha256', $path);
        DB::transaction(function () use ($import, $fileHash) {
            $current = Import::whereKey($import->id)->lockForUpdate()->firstOrFail();
            if ($current->processing_file_hash !== null && ! hash_equals($current->processing_file_hash, $fileHash)) {
                throw new \RuntimeException('Le fichier a changé depuis le début du traitement : reprise refusée.');
            }
            $current->update(['processing_file_hash' => $fileHash]);
        });

        $missing = $engine->validateHeaders($reader->headers($path, $sourceConfig), $requiredMappings);

        if ($missing !== []) {
            $import->update([
                'status' => ImportStatus::Failed,
                'error_summary' => 'Colonnes requises manquantes : '.implode(', ', $missing),
                'finished_at' => now(),
            ]);

            $import->importedByUser?->notify(new ImportProcessedNotification($import));

            return;
        }

        $import->update(['status' => ImportStatus::Processing, 'started_at' => $import->started_at ?? now(), 'heartbeat_at' => now(), 'error_summary' => null, 'finished_at' => null]);

        $chunkSize = config('imports.chunk_size', 500);
        $userId = $import->imported_by;

        $rows = LazyCollection::make(fn () => yield from $reader->read($path, $sourceConfig));
        foreach ($rows->chunk($chunkSize) as $chunk) {
            $this->processChunk($chunk, $import, $source, $mappings, $engine, $normalizer, $userId);
        }
        DB::transaction(function () use ($import) {
            $current = Import::whereKey($import->id)->lockForUpdate()->firstOrFail();
            $this->syncCounters($current);
            $current->update([
                'status' => match (true) {
                    $current->error_rows === 0 => ImportStatus::Completed,
                    $current->success_rows === 0 => ImportStatus::Failed,
                    default => ImportStatus::PartiallyCompleted,
                },
                'finished_at' => now(), 'heartbeat_at' => now(),
            ]);
        });
        $import->refresh();

        $import->importedByUser?->notify(new ImportProcessedNotification($import));
    }

    /**
     * @param  LazyCollection<int,array<string,mixed>>  $chunk  row_number => raw row
     * @param  Collection<int,SourceColumnMapping>  $mappings
     * @return array{0:int,1:int,2:int} [processed, success, error] counts for this chunk
     */
    private function processChunk(
        LazyCollection $chunk,
        Import $import,
        Source $source,
        Collection $mappings,
        MappingEngine $engine,
        TransactionNormalizer $normalizer,
        ?int $userId,
    ): array {
        return DB::transaction(function () use ($chunk, $import, $source, $mappings, $engine, $normalizer, $userId) {
            $current = Import::whereKey($import->id)->lockForUpdate()->firstOrFail();
            $chunk = $chunk->collect();
            if ($current->mapping_hash !== $import->mapping_hash) {
                throw new \RuntimeException('Le mapping de cet import a changé pendant le traitement.');
            }
            $existing = DB::table('import_rows')->where('import_id', $import->id)
                ->whereIn('row_number', $chunk->keys())->lockForUpdate()->pluck('row_number')->flip();
            $chunk = $chunk->reject(fn ($row, $number) => $existing->has($number));
            if ($chunk->isEmpty()) {
                $this->syncCounters($current);

                return [0, 0, 0];
            }
            $now = now();
            $importRowsInsert = [];
            $transactionRowsByRowNumber = [];
            $normalizedSnapshotsByRowNumber = [];
            $successCount = 0;
            $errorCount = 0;

            foreach ($chunk as $rowNumber => $rawRow) {
                $base = [
                    'import_id' => $import->id,
                    'row_number' => $rowNumber,
                    'raw_data' => json_encode($rawRow),
                    'created_at' => $now,
                    'updated_at' => $now,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ];

                try {
                    $transformed = $engine->transformRow($rawRow, $mappings);
                    $transactionRow = $normalizer->buildTransactionRow($transformed, $source, $import, $userId);
                    $snapshot = $normalizer->computeNormalizedSnapshot($transactionRow);

                    $importRowsInsert[$rowNumber] = $base + [
                        'transformed_data' => json_encode($transformed),
                        'normalized_data' => json_encode($snapshot),
                        'status' => ImportRowStatus::Imported->value,
                        'error_message' => null,
                    ];

                    $transactionRowsByRowNumber[$rowNumber] = $transactionRow;
                    $normalizedSnapshotsByRowNumber[$rowNumber] = $snapshot;
                    $successCount++;
                } catch (MissingRequiredFieldException|RowTransformException $e) {
                    $importRowsInsert[$rowNumber] = $base + [
                        'transformed_data' => null,
                        'normalized_data' => null,
                        'status' => ImportRowStatus::Error->value,
                        'error_message' => $e->getMessage(),
                    ];

                    $errorCount++;
                }
            }

            DB::table('import_rows')->insert(array_values($importRowsInsert));

            $importRowIdsByRowNumber = DB::table('import_rows')
                ->where('import_id', $import->id)
                ->whereIn('row_number', array_keys($importRowsInsert))
                ->pluck('id', 'row_number');

            if ($transactionRowsByRowNumber !== []) {
                $transactionsInsert = [];
                foreach ($transactionRowsByRowNumber as $rowNumber => $transactionRow) {
                    $transactionRow['import_row_id'] = $importRowIdsByRowNumber[$rowNumber];
                    $transactionsInsert[$rowNumber] = $transactionRow;
                }

                DB::table('transactions')->insert(array_values($transactionsInsert));

                $transactionIdsByImportRowId = DB::table('transactions')
                    ->where('import_id', $import->id)
                    ->whereIn('import_row_id', $importRowIdsByRowNumber->only(array_keys($transactionsInsert))->all())
                    ->pluck('id', 'import_row_id');

                $normalizedInsert = [];
                foreach ($transactionsInsert as $rowNumber => $transactionRow) {
                    $importRowId = $importRowIdsByRowNumber[$rowNumber];
                    $transactionId = $transactionIdsByImportRowId[$importRowId];
                    $normalizedInsert[] = $normalizer->buildNormalizedRow(
                        $transactionId,
                        $normalizedSnapshotsByRowNumber[$rowNumber],
                        $userId
                    );
                }

                DB::table('normalized_transactions')->insert($normalizedInsert);
            }

            $this->syncCounters($current);

            return [count($importRowsInsert), $successCount, $errorCount];
        });
    }

    private function syncCounters(Import $import): void
    {
        $counts = DB::table('import_rows')->where('import_id', $import->id)
            ->selectRaw("COUNT(*) AS total, COALESCE(SUM(CASE WHEN status = 'imported' THEN 1 ELSE 0 END), 0) AS accepted, COALESCE(SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END), 0) AS rejected")->lockForUpdate()->first();
        $import->update(['total_rows' => (int) $counts->total, 'processed_rows' => (int) $counts->total,
            'success_rows' => (int) $counts->accepted, 'error_rows' => (int) $counts->rejected, 'heartbeat_at' => now()]);
    }

    public function failed(Throwable $e): void
    {
        Import::query()->whereKey($this->importId)->whereNotIn('status', ['completed', 'partially_completed'])->update([
            'status' => ImportStatus::Failed->value,
            'error_summary' => $e->getMessage(),
            'job_dispatched_at' => null,
            'finished_at' => now(),
        ]);
    }
}
