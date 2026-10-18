<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Events\Login;

/**
 * Après une connexion réussie : enregistre la date de dernière connexion
 * et ajoute une entrée « login » au journal d'audit.
 * (Voir LogFailedLogin pour l'explication des Listeners.)
 * saveQuietly() enregistre sans déclencher l'Observer d'audit, pour ne pas créer
 * une deuxième entrée « updated » à chaque connexion.
 */
class LogSuccessfulLogin
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function handle(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $this->auditLogService->logEvent('login');
    }
}
