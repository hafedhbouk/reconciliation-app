<?php

namespace App\Models;

use App\Enums\MatchingResultStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Résultat d'un rapprochement : « ce groupe de transactions A correspond à ce groupe B ».
 *
 * Le résultat lui-même ne contient pas les transactions. Elles sont listées
 * dans MatchingDetail (une ligne par transaction, avec le côté 'a' ou 'b').
 *
 *   MatchingResult #1 (matched)
 *     ├─ MatchingDetail : transaction ALPHA #120, côté a
 *     └─ MatchingDetail : transaction BNA   #845, côté b
 *
 * (Les notions fillable, casts, relations et traits sont expliquées en
 * détail dans app/Models/Import.php.)
 */
class MatchingResult extends Model
{
    use Auditable, HasFactory, HasUserstamps, SoftDeletes;

    protected $fillable = [
        'comparison_run_id', // la comparaison de fichiers qui a produit ce résultat (null pour une règle globale)
        'matching_rule_id',
        'batch_reference',   // identifiant du « lot » : tous les résultats d'une même exécution le partagent
        'status',            // voir App\Enums\MatchingResultStatus
        'confidence_score',  // note de confiance de 0 à 100 (voir ConfidenceScorer)
        'matched_by',        // utilisateur, si le rapprochement est manuel (null si automatique)
        'matched_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => MatchingResultStatus::class,
            'confidence_score' => 'decimal:2', // nombre avec 2 décimales
            'matched_at' => 'datetime',
        ];
    }

    public function matchingRule(): BelongsTo
    {
        return $this->belongsTo(MatchingRule::class);
    }

    public function comparisonRun(): BelongsTo
    {
        return $this->belongsTo(ComparisonRun::class);
    }

    public function matchedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    /** Les transactions qui composent ce résultat (côtés a et b). */
    public function matchingDetails(): HasMany
    {
        return $this->hasMany(MatchingDetail::class);
    }

    /** Les exceptions créées pour ce résultat (ex. écart de montant). */
    public function exceptions(): HasMany
    {
        return $this->hasMany(ExceptionRecord::class);
    }
}
