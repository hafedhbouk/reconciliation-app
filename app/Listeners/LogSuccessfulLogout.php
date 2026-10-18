<?php

namespace App\Listeners;

use App\Services\AuditLogService;
use Illuminate\Auth\Events\Logout;

/**
 * Inscrit chaque déconnexion dans le journal d'audit.
 * (Voir LogFailedLogin pour l'explication des Listeners.)
 */
class LogSuccessfulLogout
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function handle(Logout $event): void
    {
        $this->auditLogService->logEvent('logout');
    }
}
