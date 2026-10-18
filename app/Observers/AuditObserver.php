<?php

namespace App\Observers;

use App\Models\UnmatchedSnapshot;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

/**
 * Écrit une entrée dans le journal d'audit à chaque modification d'un modèle « Auditable ».
 *
 * Un « Observer » est une classe dont les méthodes sont appelées
 * automatiquement par Laravel lors des événements d'un modèle :
 * created() après une création, updated() après une modification, etc.
 * Il est branché par le trait App\Models\Concerns\Auditable.
 *
 * Attention : les insertions en masse (DB::table()->insert(), utilisées par
 * les imports et le rapprochement) ne passent PAS par l'Observer, d'où leur
 * absence du journal d'audit (choix assumé, voir ProcessImportJob).
 */
class AuditObserver
{
    public function __construct(private AuditLogService $auditLogService) {}

    /** Après création : on enregistre toutes les valeurs de la nouvelle ligne. */
    public function created(Model $model): void
    {
        $this->auditLogService->logModelEvent('created', $model, null, $this->auditableAttributes($model));
    }

    /** Après modification : on enregistre seulement les colonnes modifiées, avant et après. */
    public function updated(Model $model): void
    {
        // getChanges() = les colonnes réellement modifiées par le dernier enregistrement.
        $changes = $this->auditableAttributes($model, $model->getChanges());

        if (empty($changes)) {
            return;
        }

        // Anciennes valeurs de ces mêmes colonnes. Pour UnmatchedSnapshot on lit la
        // valeur brute, car son accesseur result_a/result_b irait relire toute la table de lignes.
        $original = array_intersect_key(($model instanceof UnmatchedSnapshot ? $model->getRawOriginal() : $model->getOriginal()), $changes);

        $this->auditLogService->logModelEvent('updated', $model, $original, $changes);
    }

    /** Après suppression : on garde une copie des valeurs supprimées. */
    public function deleted(Model $model): void
    {
        $this->auditLogService->logModelEvent('deleted', $model, $this->auditableAttributes($model), null);
    }

    /** Après restauration d'un élément supprimé (soft delete). */
    public function restored(Model $model): void
    {
        $this->auditLogService->logModelEvent('restored', $model, null, $this->auditableAttributes($model));
    }

    /** Retire les colonnes à ne jamais journaliser (mot de passe…), voir Auditable::auditExcludedAttributes(). */
    private function auditableAttributes(Model $model, ?array $attributes = null): array
    {
        // `??=` : n'affecte que si $attributes est null.
        $attributes ??= $model->getAttributes();

        $excluded = method_exists($model, 'auditExcludedAttributes')
            ? $model->auditExcludedAttributes()
            : [];

        // array_diff_key retire du tableau les clés présentes dans la liste d'exclusion.
        return array_diff_key($attributes, array_flip($excluded));
    }
}
