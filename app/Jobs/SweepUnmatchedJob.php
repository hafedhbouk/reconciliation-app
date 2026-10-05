<?php

namespace App\Jobs;

/**
 * Balaie les transactions toujours non rapprochées après un batch de matching.
 *
 * Chaque normalized_transaction en statut Unmatched se voit créer une
 * exception de type Unmatched, sauf si une exception ouverte/en revue
 * existe déjà. Peut être limité à une source spécifique via sourceId.
 */
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

    public function __construct(
        public ?int $sourceId = null,
        public ?int $notifyUserId = null,
        public ?int $importId = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?string $batchReference = null,
    ) {}

    public function handle(UnmatchedSweeper $sweeper): void
    {
        $created = $sweeper->sweep(
            $this->sourceId,
            $this->importId,
            $this->dateFrom,
            $this->dateTo,
            $this->batchReference,
        );

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
