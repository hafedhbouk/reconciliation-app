<?php

namespace App\Jobs;

use App\Services\ResultEmails;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envoie les résultats d'un rapprochement aux destinataires en file d'attente
 * (voir App\Services\ResultEmails, qui note le résultat de chaque envoi).
 *
 * Job distinct du rapprochement : son échec ne peut pas faire échouer le
 * rapprochement. Une seule tentative : une nouvelle tentative automatique
 * risquerait d'envoyer deux fois le même e-mail ; un envoi en échec se relance
 * depuis l'application (bouton « Renvoyer »).
 * (Le fonctionnement général des jobs est expliqué dans ProcessImportJob.php.)
 */
class SendResultEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $operationId) {}

    public function handle(ResultEmails $emails): void
    {
        $emails->deliver($this->operationId);
    }

    public function failed(Throwable $e): void
    {
        Log::error('SendResultEmailsJob failed', ['operation_id' => $this->operationId, 'error' => $e->getMessage()]);
    }
}
