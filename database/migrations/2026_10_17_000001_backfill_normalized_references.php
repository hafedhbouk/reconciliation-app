<?php

use App\Services\Matching\SnapshotRows;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aligne les lignes existantes sur TransactionNormalizer::computeNormalizedSnapshot() :
 * - BNA   : normalized_reference = N° autorisation, pas de référence secondaire ;
 * - ALPHA : normalized_secondary_reference = NUM_AUTO ;
 * - WEB   : normalized_secondary_reference = recu_paie.
 * Corrige aussi import_rows.normalized_data (affiché sur la page de l'import),
 * y compris la clé normalized_secondary_reference ajoutée à tort aux lignes BNA.
 * Idempotente : ne réécrit que les lignes dont la valeur change.
 */
return new class extends Migration
{
    public function up(): void
    {
        $changedImports = [];

        DB::table('normalized_transactions as n')
            ->join('transactions as t', 't.id', '=', 'n.transaction_id')
            ->leftJoin('import_rows as r', 'r.id', '=', 't.import_row_id')
            ->select(['n.id as normalized_id', 'n.normalized_reference', 'n.normalized_secondary_reference',
                't.import_id', 't.external_reference', 't.raw_payload', 'r.id as import_row_id', 'r.normalized_data'])
            ->orderBy('n.id')
            ->chunkById(500, function (Collection $rows) use (&$changedImports) {
                $references = [];
                $secondaryReferences = [];
                $importRows = [];

                foreach ($rows as $row) {
                    $payload = json_decode((string) $row->raw_payload, true) ?? [];
                    $authorization = $payload['num_autorisation'] ?? $payload['secondary_reference'] ?? null;
                    $external = $row->external_reference;
                    $reference = (string) ($external ?? $authorization ?? $row->normalized_reference);
                    $secondary = ($external !== null && $authorization !== null && (string) $authorization !== (string) $external)
                        ? (string) $authorization : null;

                    if ($reference !== (string) $row->normalized_reference || $secondary !== $row->normalized_secondary_reference) {
                        $references[$row->normalized_id] = $reference;
                        $secondaryReferences[$row->normalized_id] = $secondary;
                        $changedImports[$row->import_id] = true;
                    }

                    if ($row->import_row_id !== null && $row->normalized_data !== null) {
                        $data = json_decode((string) $row->normalized_data, true) ?? [];
                        $fixed = $data;
                        $fixed['normalized_reference'] = $reference;
                        unset($fixed['normalized_secondary_reference']);
                        if ($secondary !== null) {
                            $fixed['normalized_secondary_reference'] = $secondary;
                        }
                        if ($fixed != $data) {
                            $importRows[$row->import_row_id] = json_encode($fixed, JSON_THROW_ON_ERROR);
                        }
                    }
                }

                $this->updateById('normalized_transactions', 'normalized_reference', $references);
                $this->updateById('normalized_transactions', 'normalized_secondary_reference', $secondaryReferences);
                $this->updateById('import_rows', 'normalized_data', $importRows);
            }, 'n.id', 'normalized_id');

        // Cached file comparisons display the old references: force a rerun.
        if ($changedImports !== []) {
            app(SnapshotRows::class)->invalidate(array_keys($changedImports));
        }
    }

    public function down(): void
    {
        // Data correction only; the previous values were inconsistent.
    }

    private function updateById(string $table, string $column, array $updates): void
    {
        if ($updates === []) {
            return;
        }

        $cases = [];
        $bindings = [];
        foreach ($updates as $id => $value) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $id;
            $bindings[] = $value;
        }
        $placeholders = implode(',', array_fill(0, count($updates), '?'));
        $statement = "UPDATE {$table} SET {$column} = CASE id ".implode(' ', $cases)." ELSE {$column} END WHERE id IN ({$placeholders})";

        DB::update($statement, [...$bindings, ...array_keys($updates)]);
    }
};
