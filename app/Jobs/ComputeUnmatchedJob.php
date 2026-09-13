<?php

namespace App\Jobs;

use App\Models\UnmatchedSnapshot;
use App\Services\Matching\RuleMatcher;
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
    ) {}

    public function handle(): void
    {
        $snapshot = UnmatchedSnapshot::query()->findOrFail($this->snapshotId);
        $snapshot->update(['status' => 'processing', 'started_at' => now()]);

        try {
            app(RuleMatcher::class)->refreshFileDifferences($snapshot->importA, $snapshot->importB);
        } catch (Throwable $e) {
            $this->failed($e);
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
}
