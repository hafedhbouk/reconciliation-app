<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une exécution de « comparaison de fichiers » : le fichier A comparé au fichier B.
 *
 * Contrairement à une règle globale (qui compare toutes les transactions de
 * deux sources), une comparaison porte sur deux imports précis. On y garde
 * le résumé chiffré, les totaux par catégorie et les id des lignes
 * présentes d'un seul côté.
 *
 * invalidated_at : rempli quand les données des fichiers ont été
 * renormalisées. Le résumé n'est alors plus fiable et il faut relancer la
 * comparaison avec un nouveau lot.
 * (Les notions fillable, casts et relations sont expliquées dans app/Models/Import.php.)
 */
class ComparisonRun extends Model
{
    protected $fillable = [
        'file_totals', 'invalidated_at',
        'import_a_id', 'import_b_id', 'batch_reference', 'criteria',
        'summary', 'unmatched_a_ids', 'unmatched_b_ids',
    ];

    protected function casts(): array
    {
        return ['invalidated_at' => 'datetime', 'file_totals' => 'array', 'criteria' => 'array', 'summary' => 'array', 'unmatched_a_ids' => 'array', 'unmatched_b_ids' => 'array'];
    }

    /**
     * Le fichier A. withTrashed() inclut aussi un import supprimé (soft delete),
     * pour que l'historique d'une comparaison reste consultable.
     */
    public function importA(): BelongsTo
    {
        return $this->belongsTo(Import::class, 'import_a_id')->withTrashed();
    }

    /** Le fichier B (même remarque que importA()). */
    public function importB(): BelongsTo
    {
        return $this->belongsTo(Import::class, 'import_b_id')->withTrashed();
    }
}
