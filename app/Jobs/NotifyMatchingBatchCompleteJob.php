<?php

namespace App\Jobs;

use App\Models\ExceptionRecord;
use App\Models\MatchingResult;
use App\Models\User;
use App\Notifications\MatchingActionCompletedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Trailing job appended to "Lancer tout"'s Bus::chain, after every rule and
 * both sweepers. One aggregate summary instead of a notification per rule --
 * a 6-rule chain notifying individually would spam the bell for one click.
 */
class NotifyMatchingBatchCompleteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 0;

    public function __construct(public string $batchReference, public int $notifyUserId)
    {
    }

    public function handle(): void
    {
        $results = MatchingResult::query()
            ->where('batch_reference', $this->batchReference)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $batchStartedAt = MatchingResult::query()
            ->where('batch_reference', $this->batchReference)
            ->min('matched_at');

        $exceptionsCreated = $batchStartedAt !== null
            ? ExceptionRecord::query()->where('created_at', '>=', $batchStartedAt)->count()
            : 0;

        $lines = $results->map(fn ($count, $status) => __(':count :status', ['count' => $count, 'status' => $status]))->values()->all();
        $lines[] = __(':count exceptions créées', ['count' => $exceptionsCreated]);

        User::query()->find($this->notifyUserId)?->notify(new MatchingActionCompletedNotification(
            __('Lancer tout terminé'),
            $lines,
        ));
    }

    public function failed(Throwable $e): void
    {
        Log::error('NotifyMatchingBatchCompleteJob failed', [
            'batch_reference' => $this->batchReference,
            'error' => $e->getMessage(),
        ]);
    }
}
