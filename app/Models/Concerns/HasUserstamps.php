<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Trait qui remplit automatiquement created_by et updated_by avec l'id de
 * l'utilisateur connecté. Laravel fait déjà la même chose pour les dates
 * (created_at / updated_at) ; ce trait fait de même pour l'auteur.
 * (Voir Auditable.php pour l'explication des traits et de la méthode boot.)
 */
trait HasUserstamps
{
    public static function bootHasUserstamps(): void
    {
        // « creating » : juste avant l'INSERT d'une nouvelle ligne.
        static::creating(function ($model) {
            // Auth::check() = un utilisateur est connecté (faux dans un job ou une commande).
            // isDirty('created_by') = le code a déjà rempli la valeur : on ne l'écrase pas.
            if (Auth::check() && ! $model->isDirty('created_by')) {
                $model->created_by = Auth::id();
            }
        });

        // « updating » : juste avant l'UPDATE d'une ligne existante.
        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    /** L'utilisateur qui a créé la ligne. */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Le dernier utilisateur à avoir modifié la ligne. */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
