<?php

namespace App\Jobs;

use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Exceptions\Import\MissingRequiredFieldException;
use App\Exceptions\Import\RowTransformException;
use App\Models\Import;
use App\Models\Source;
use App\Models\SourceColumnMapping;
use App\Services\Import\MappingEngine;
use App\Services\Import\Readers\ImportRowReaderFactory;
use App\Services\Import\TransactionNormalizer;
use Illuminate\Bus\Queueable;
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

    public function __construct(public int $importId)
    {
    }

    public function handle(ImportRowReaderFactory $readerFactory, MappingEngine $engine, TransactionNormalizer $normalizer): void
    {
        $import = Import::query()->with('source')->findOrFail($this->importId);
        $source = $import->source;
        $mappings = SourceColumnMapping::query()->where('source_id', $source->id)->get();
        $requiredMappings = $mappings->where('is_required', true);

        $reader = $readerFactory->make($source);
        $path = Storage::path($import->stored_path);
        $sourceConfig = $source->config ?? [];

        $missing = $engine->validateHeaders($reader->headers($path, $sourceConfig), $requiredMappings);

        if ($missing !== []) {
            $import->update([
                'status' => ImportStatus::Failed,
                'error_summary' => 'Colonnes requises manquantes : '.implode(', ', $missing),
                'finished_at' => now(),
            ]);

            return;
        }

        $import->update(['status' => ImportStatus::Processing, 'started_at' => now()]);

        $chunkSize = config('imports.chunk_size', 500);
        $userId = $import->imported_by;

        $processed = 0;
        $success = 0;
        $errors = 0;

        $rows = LazyCollection::make(fn () => yield from $reader->read($path, $sourceConfig));

        foreach ($rows->chunk($chunkSize) as $chunk) {
            [$chunkProcessed, $chunkSuccess, $chunkErrors] = $this->processChunk(
                $chunk, $import, $source, $mappings, $engine, $normalizer, $userId
            );

            $processed += $chunkProcessed;
            $success += $chunkSuccess;
            $errors += $chunkErrors;

            $import->update([
                'total_rows' => $processed,
                'processed_rows' => $processed,
                'success_rows' => $success,
                'error_rows' => $errors,
            ]);
        }

        $import->update([
            'status' => match (true) {
                $errors === 0 => ImportStatus::Completed,
                $success === 0 => ImportStatus::Failed,
                default => ImportStatus::PartiallyCompleted,
            },
            'finished_at' => now(),
        ]);
    }

    /**
     * @param LazyCollection<int,array<string,mixed>> $chunk row_number => raw row
     * @param Collection<int,SourceColumnMapping> $mappings
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
                    $transactionRow = $normalizer->buildTransactionRow($transformed, $source, $import, $userId ?? 0);
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
                        $userId ?? 0
                    );
                }

                DB::table('normalized_transactions')->insert($normalizedInsert);
            }

            return [count($importRowsInsert), $successCount, $errorCount];
        });
    }

    public function failed(Throwable $e): void
    {
        Import::query()->whereKey($this->importId)->update([
            'status' => ImportStatus::Failed->value,
            'error_summary' => $e->getMessage(),
            'finished_at' => now(),
        ]);
    }
}
