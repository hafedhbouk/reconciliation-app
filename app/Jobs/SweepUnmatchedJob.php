<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\MatchingActionCompletedNotification;
use App\Services\Matching\UnmatchedSweeper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SweepUnmatchedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 0;

    public function __construct(public ?int $sourceId = null, public ?int $notifyUserId = null)
    {
    }

    public function handle(UnmatchedSweeper $sweeper): void
    {
        $created = $sweeper->sweep($this->sourceId);

        if ($this->notifyUserId !== null) {
            User::query()->find($this->notifyUserId)?->notify(new MatchingActionCompletedNotification(
                __('Balayage des non-rapprochés terminé'),
                [__(':count exceptions créées', ['count' => $created])],
            ));
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('SweepUnmatchedJob failed', [
            'source_id' => $this->sourceId,
            'error' => $e->getMessage(),
        ]);
    }
}
