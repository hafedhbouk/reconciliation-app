<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Journal d'audit : qui a fait quoi, quand, et sur quelle donnée.
 *
 * Une ligne est créée automatiquement par AuditObserver à chaque
 * création / modification / suppression d'un modèle qui utilise le trait
 * Auditable, ainsi qu'à chaque connexion ou déconnexion (dossier Listeners).
 * On ne modifie jamais une ligne d'audit, d'où l'absence de updated_at.
 * (Les notions fillable, casts et relations sont expliquées dans app/Models/Import.php.)
 */
class AuditLog extends Model
{
    public $timestamps = true;

    // Pas de colonne updated_at : une entrée d'audit n'est jamais modifiée.
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',         // auteur de l'action (null si action système)
        'event',           // 'created', 'updated', 'deleted', 'login'…
        'auditable_type',  // classe du modèle concerné, ex. 'App\Models\Import'
        'auditable_id',    // id de la ligne concernée
        'old_values',      // valeurs avant la modification
        'new_values',      // valeurs après la modification
        'url',
        'ip_address',
        'user_agent',      // navigateur utilisé
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation « polymorphe » (MorphTo) : la ligne auditée peut appartenir à
     * N'IMPORTE QUEL modèle (Import, Source, User…). Laravel lit
     * auditable_type pour savoir dans quelle table chercher auditable_id.
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
