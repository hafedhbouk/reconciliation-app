<?php

namespace App\Listeners;

use App\Services\AuditLogService;
use Illuminate\Auth\Events\Failed;

/**
 * Inscrit chaque tentative de connexion ratée dans le journal d'audit (avec l'email essayé).
 *
 * Un « Listener » réagit à un événement : Laravel déclenche l'événement Failed
 * quand une connexion échoue, et appelle handle(). Le branchement
 * événement -> listener est fait dans AppServiceProvider::boot().
 */
class LogFailedLogin
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function handle(Failed $event): void
    {
        $this->auditLogService->logEvent('login_failed', [
            'email' => $event->credentials['email'] ?? null,
        ]);
    }
}
