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

/**
 * Calcule en arrière-plan les écarts entre deux fichiers (lignes présentes d'un seul côté).
 *
 * Contrairement à RunAdHocMatchingJob, ce job ne crée AUCUN rapprochement
 * et ne change aucun statut : il remplit seulement le cache UnmatchedSnapshot
 * affiché sur l'écran « Écarts entre fichiers ».
 * (Le fonctionnement général des jobs est expliqué dans ProcessImportJob.php.)
 */
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
            // RuleMatcher remplit le cache et passe le statut à « completed » quand il a fini.
            app(RuleMatcher::class)->refreshFileDifferences($snapshot->importA, $snapshot->importB);
        } catch (Throwable $e) {
            // En cas d'erreur : on l'écrit dans le journal, et on l'affiche sur le cache pour l'utilisateur.
            $this->failed($e);
            $snapshot->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }

    /** Écrit l'erreur dans le journal de Laravel (storage/logs/laravel.log). */
    public function failed(Throwable $e): void
    {
        Log::error('ComputeUnmatchedJob failed', [
            'snapshot_id' => $this->snapshotId,
            'error' => $e->getMessage(),
        ]);
    }
}
