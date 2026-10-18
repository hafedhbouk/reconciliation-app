<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Écrit les entrées du journal d'audit (table audit_logs).
 *
 * Deux usages :
 *   - logModelEvent() : une donnée a changé (appelé par AuditObserver) ;
 *   - logEvent()      : un événement sans donnée associée (connexion, déconnexion…).
 * Chaque entrée garde aussi l'utilisateur, l'URL, l'adresse IP et le navigateur.
 */
class AuditLogService
{
    public function logModelEvent(string $event, Model $model, ?array $oldValues, ?array $newValues): void
    {
        $this->log($event, $oldValues, $newValues, $model);
    }

    /** @param  array  $context  informations complémentaires, ex. ['email' => '...'] */
    public function logEvent(string $event, array $context = []): void
    {
        // `$context ?: null` : un tableau vide est enregistré comme null.
        $this->log($event, null, $context ?: null, null);
    }

    private function log(string $event, ?array $oldValues, ?array $newValues, ?Model $auditable): void
    {
        AuditLog::query()->create([
            'user_id' => Auth::id(),                         // null si personne n'est connecté (job, commande)
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(), // ex. 'App\Models\Import'
            'auditable_id' => $auditable?->getKey(),          // ex. 9
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'url' => Request::fullUrl(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
