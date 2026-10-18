<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use App\Services\Matching\SnapshotRows;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Résultat mis en cache de la comparaison « fichier A ↔ fichier B » : les lignes sans correspondance.
 *
 * Calculer les écarts entre deux fichiers de 80 000 lignes prend du temps.
 * ComputeUnmatchedJob le fait une fois en arrière-plan et range le résultat
 * ici ; les écrans relisent ensuite ce cache au lieu de tout recalculer.
 *
 * Les lignes elles-mêmes sont dans la table unmatched_snapshot_rows
 * (rows_persisted = vrai). Les anciennes versions les stockaient en JSON
 * dans result_a / result_b, ce que les « accesseurs » ci-dessous gèrent encore.
 * (Les notions fillable, casts, relations et traits sont expliquées dans app/Models/Import.php.)
 */
class UnmatchedSnapshot extends Model
{
    use Auditable, HasFactory, HasUserstamps;

    protected $table = 'unmatched_snapshots';

    protected $fillable = [
        'rows_persisted', 'file_totals', // file_totals : nombre de lignes et montants par catégorie et par côté
        'import_a_id',
        'import_b_id',
        'status',       // 'pending', 'processing', 'completed' ou 'failed'
        'result_a',     // ancien format : lignes seulement dans A (JSON)
        'result_b',     // ancien format : lignes seulement dans B (JSON)
        'error',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'rows_persisted' => 'boolean', 'file_totals' => 'array',
            'result_a' => 'array',
            'result_b' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function importA(): BelongsTo
    {
        return $this->belongsTo(Import::class, 'import_a_id');
    }

    /**
     * « Accesseur » : une méthode nommée get<NomColonne>Attribute est appelée
     * automatiquement quand on lit $snapshot->result_a. On renvoie ainsi les
     * lignes quel que soit leur lieu de stockage.
     *
     * Compatibility for explicit full exports; screens use SnapshotRows::paginate.
     */
    public function getResultAAttribute($value): ?array
    {
        return $this->resultRows('a', $value);
    }

    /** Même chose pour $snapshot->result_b. */
    public function getResultBAttribute($value): ?array
    {
        return $this->resultRows('b', $value);
    }

    /**
     * Renvoie les lignes d'un côté ('a' ou 'b').
     * - Nouveau format : les lire dans unmatched_snapshot_rows (seulement si le calcul est terminé).
     * - Ancien format : décoder le JSON stocké dans la colonne.
     */
    private function resultRows(string $side, $value): ?array
    {
        if ($this->rows_persisted) {
            if ($this->status !== 'completed') {
                return null;
            }

            return app(SnapshotRows::class)->query($this->id, $side)->get(['data'])
                ->map(fn ($row) => json_decode($row->data, true))->all();
        }

        return is_string($value) ? json_decode($value, true) : $value;
    }

    public function importB(): BelongsTo
    {
        return $this->belongsTo(Import::class, 'import_b_id');
    }
}
