<?php

namespace App\Jobs;

/**
 * Lance une détection de doublons sur les transactions normalisées.
 *
 * Déclenché manuellement depuis l'interface admin. Le scan couvre
 * toutes les sources ou une source spécifique, selon le paramètre
 * sourceId. Un résumé (groupes trouvés, exceptions créées) est notifié
 * à l'utilisateur si notifyUserId est fourni.
 */
use App\Models\User;
use App\Notifications\MatchingActionCompletedNotification;
use App\Services\Matching\DuplicateDetector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class DetectDuplicatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 0;

    public function __construct(public ?int $sourceId = null, public ?int $notifyUserId = null)
    {
    }

    public function handle(DuplicateDetector $detector): void
    {
        $summary = $detector->scan($this->sourceId);

        if ($this->notifyUserId !== null) {
            User::query()->find($this->notifyUserId)?->notify(new MatchingActionCompletedNotification(
                __('Détection des doublons terminée'),
                [
                    __(':count groupes détectés', ['count' => $summary->groupsFound]),
                    __(':count exceptions créées', ['count' => $summary->exceptionsCreated]),
                ],
            ));
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('DetectDuplicatesJob failed', [
            'source_id' => $this->sourceId,
            'error' => $e->getMessage(),
        ]);
    }
}
