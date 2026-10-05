<?php

namespace App\Services\Matching;

use App\Models\ComparisonRun;
use App\Models\NormalizedTransaction;
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
        $page = $this->query($snapshot->id, $side)->paginate($perPage, ['data'], 'page_'.$side)
            ->withQueryString();

        $snapshot->loadMissing(['importA.source', 'importB.source']);
        $codes = [
            strtoupper($snapshot->importA?->source?->code ?? ''),
            strtoupper($snapshot->importB?->source?->code ?? ''),
        ];
        $isAlphaBna = in_array('ALPHA', $codes, true) && in_array('BNA', $codes, true);
        $isAlphaWeb = in_array('ALPHA', $codes, true)
            && (in_array('WEB', $codes, true) || in_array('STEG', $codes, true));
        $isBnaWeb = in_array('BNA', $codes, true)
            && (in_array('WEB', $codes, true) || in_array('STEG', $codes, true));

        if (! $isAlphaBna && ! $isAlphaWeb && ! $isBnaWeb) {
            return $page->through(fn ($row) => json_decode($row->data, true, 512, JSON_THROW_ON_ERROR));
        }

        $decodedRows = $page->getCollection()->map(fn ($row) => json_decode($row->data, true, 512, JSON_THROW_ON_ERROR));
        $fieldsById = NormalizedTransaction::query()->with('transaction:id,raw_payload')
            ->whereIn('id', $decodedRows->pluck('id')->filter())
            ->get(['id', 'transaction_id'])
            ->mapWithKeys(fn (NormalizedTransaction $transaction) => [$transaction->id => [
                'num_autorisation' => $transaction->transaction?->raw_payload['num_autorisation'] ?? null,
                'secondary_reference' => $transaction->transaction?->raw_payload['secondary_reference'] ?? null,
            ]]);

        return $page->through(function ($row) use ($fieldsById) {
            $data = json_decode($row->data, true, 512, JSON_THROW_ON_ERROR);
            foreach (['num_autorisation', 'secondary_reference'] as $field) {
                if (! isset($data[$field])) {
                    $data[$field] = $fieldsById[$data['id']][$field] ?? null;
                }
            }

            return $data;
        });
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
