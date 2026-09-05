<?php

namespace App\Jobs;

use App\Models\Import;
use App\Models\NormalizedTransaction;
use App\Models\UnmatchedSnapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ComputeUnmatchedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 0;

    public function __construct(
        public int $snapshotId,
        public ?int $notifyUserId = null
    ) {
    }

    public function handle(): void
    {
        $snapshot = UnmatchedSnapshot::query()->findOrFail($this->snapshotId);

        $snapshot->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        try {
            $importA = $snapshot->importA;
            $importB = $snapshot->importB;

            $primaryKey = $this->resolvePrimaryKey($importA->source, $importB->source);

            $candidatesA = $this->loadCandidates($snapshot->import_a_id, $primaryKey['a']);
            $candidatesB = $this->loadCandidates($snapshot->import_b_id, $primaryKey['b']);

            $keysA = $candidatesA->keys()->sort()->values();
            $keysB = $candidatesB->keys()->sort()->values();

            $onlyAKeys = $keysA->diff($keysB);
            $onlyBKeys = $keysB->diff($keysA);

            $resultA = $onlyAKeys->flatMap(fn ($key) => $candidatesA->get($key, collect()))->values();
            $resultB = $onlyBKeys->flatMap(fn ($key) => $candidatesB->get($key, collect()))->values();

            $snapshot->update([
                'status' => 'completed',
                'result_a' => $resultA->map(fn ($nt) => $this->formatTransaction($nt))->all(),
                'result_b' => $resultB->map(fn ($nt) => $this->formatTransaction($nt))->all(),
                'completed_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('ComputeUnmatchedJob failed', [
                'snapshot_id' => $this->snapshotId,
                'error' => $e->getMessage(),
            ]);

            $snapshot->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('ComputeUnmatchedJob failed', [
            'snapshot_id' => $this->snapshotId,
            'error' => $e->getMessage(),
        ]);
    }

    /** @return Collection<string,Collection<int,NormalizedTransaction>> */
    private function loadCandidates(int $importId, string $primaryKey): \Illuminate\Support\Collection
    {
        $rows = NormalizedTransaction::query()
            ->join('transactions', 'transactions.id', '=', 'normalized_transactions.transaction_id')
            ->where('transactions.import_id', $importId)
            ->where('normalized_transactions.matching_status', 'unmatched')
            ->select('normalized_transactions.*', 'transactions.raw_payload')
            ->get();

        return $rows->groupBy(fn (NormalizedTransaction $nt) => $this->primaryKeyValue($nt, $primaryKey));
    }

    private function primaryKeyValue(NormalizedTransaction $nt, string $primaryKey): string
    {
        if ($primaryKey === 'date|amount') {
            return $nt->normalized_date->format('Y-m-d').'|'.$nt->normalized_amount_millimes;
        }

        if ($primaryKey === 'reference') {
            return (string) $nt->normalized_reference;
        }

        $payload = $this->rawPayload($nt);

        return (string) ($payload[$primaryKey] ?? '');
    }

    /** @return array<string, mixed> */
    private function rawPayload(NormalizedTransaction $nt): array
    {
        $payload = $nt->getAttribute('raw_payload');

        if (is_string($payload)) {
            $payload = json_decode($payload, true) ?? [];
        }

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array{a:string,b:string}
     */
    private function resolvePrimaryKey(\App\Models\Source $sourceA, \App\Models\Source $sourceB): array
    {
        $codeA = strtoupper($sourceA->code);
        $codeB = strtoupper($sourceB->code);

        if (($codeA === 'ALPHA' && $codeB === 'BNA') || ($codeA === 'BNA' && $codeB === 'ALPHA')) {
            return ['a' => 'num_autorisation', 'b' => 'num_autorisation'];
        }

        if (($codeA === 'ALPHA' && $codeB === 'WEB') || ($codeA === 'WEB' && $codeB === 'ALPHA')) {
            return ['a' => 'reference', 'b' => 'reference'];
        }

        if ($codeA === 'SMT' || $codeB === 'SMT') {
            return ['a' => 'date|amount', 'b' => 'date|amount'];
        }

        if (($codeA === 'WEB' && $codeB === 'BNA') || ($codeA === 'BNA' && $codeB === 'WEB')) {
            $aIsWeb = $codeA === 'WEB';

            return [
                'a' => $aIsWeb ? 'reference' : 'num_autorisation',
                'b' => $aIsWeb ? 'num_autorisation' : 'reference',
            ];
        }

        return ['a' => 'num_autorisation', 'b' => 'num_autorisation'];
    }

    /** @return array<string, mixed> */
    private function formatTransaction(NormalizedTransaction $nt): array
    {
        $payload = $this->rawPayload($nt);

        return [
            'id' => $nt->id,
            'source' => $nt->transaction->source->code ?? 'N/A',
            'reference' => $nt->normalized_reference,
            'amount_millimes' => $nt->normalized_amount_millimes,
            'date' => $nt->normalized_date?->format('d/m/Y'),
            'primary_key_value' => $payload['num_autorisation']
                ?? $payload['reference']
                ?? $nt->normalized_reference,
        ];
    }
}
