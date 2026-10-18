<?php

namespace App\Models\Concerns;

use App\Observers\AuditObserver;

/**
 * Trait à ajouter à un modèle pour que toutes ses modifications soient journalisées.
 *
 * Pour un débutant : un « trait » est un morceau de code qu'on « colle » dans
 * plusieurs classes avec `use Auditable;`, sans héritage.
 *
 * Fonctionnement : Laravel appelle automatiquement toute méthode statique
 * nommée boot<NomDuTrait>() (ici bootAuditable) au premier chargement du
 * modèle. On y branche AuditObserver, qui écrit dans audit_logs à chaque
 * création, modification ou suppression.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::observe(AuditObserver::class);
    }

    /**
     * Attributes never written to audit_logs (secrets or high-churn noise).
     * Un modèle peut redéfinir cette méthode pour exclure d'autres colonnes.
     */
    public function auditExcludedAttributes(): array
    {
        return ['password', 'remember_token'];
    }
}
