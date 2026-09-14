<?php

namespace App\Services\Matching;

use App\Models\ComparisonRun;
use App\Models\UnmatchedSnapshot;
use Illuminate\Support\Facades\DB;

class SnapshotRows
{
    public function ensureStored(UnmatchedSnapshot $snapshot): void
    {
        if ($snapshot->rows_persisted) {
            return;
        }
        DB::transaction(function () use ($snapshot) {
            $current = UnmatchedSnapshot::whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
            if ($current->rows_persisted) {
                return;
            }
            foreach (['a', 'b'] as $side) {
                $rows = json_decode($current->getRawOriginal('result_'.$side) ?? '[]', true) ?? [];
                foreach (array_chunk($rows, 500) as $chunk) {
                    $this->insert($snapshot->id, $side, $chunk);
                }
            }
            $current->update(['rows_persisted' => true, 'result_a' => null, 'result_b' => null]);
        });
        $snapshot->refresh();
    }

    public function insert(int $snapshotId, string $side, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        DB::table('unmatched_snapshot_rows')->insert(array_map(fn ($row) => [
            'snapshot_id' => $snapshotId, 'side' => $side, 'normalized_transaction_id' => $row['id'],
            'data' => json_encode($row, JSON_THROW_ON_ERROR),
        ], $rows));
    }

    public function query(int $snapshotId, string $side)
    {
        return DB::table('unmatched_snapshot_rows')->where('snapshot_id', $snapshotId)->where('side', $side)->orderBy('normalized_transaction_id');
    }

    public function paginate(UnmatchedSnapshot $snapshot, string $side, int $perPage = 50)
    {
        $this->ensureStored($snapshot);

        return $this->query($snapshot->id, $side)->paginate($perPage, ['data'], 'page_'.$side)
            ->through(fn ($row) => json_decode($row->data, true, 512, JSON_THROW_ON_ERROR))->withQueryString();
    }

    public function invalidate(array $importIds): void
    {
        ComparisonRun::whereIn('import_a_id', $importIds)->orWhereIn('import_b_id', $importIds)->update(['invalidated_at' => now()]);
        $ids = UnmatchedSnapshot::whereIn('import_a_id', $importIds)->orWhereIn('import_b_id', $importIds)->pluck('id');
        DB::table('unmatched_snapshot_rows')->whereIn('snapshot_id', $ids)->delete();
        UnmatchedSnapshot::whereIn('id', $ids)->update([
            'status' => 'failed', 'result_a' => null, 'result_b' => null, 'rows_persisted' => true,
            'file_totals' => null, 'completed_at' => null,
            'error' => 'Données renormalisées : relancer la comparaison.',
        ]);
    }
}
