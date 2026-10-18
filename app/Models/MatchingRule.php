<?php

namespace App\Models;

/**
 * Règle de rapprochement entre deux sources de transactions.
 *
 * Définit les sources à comparer (source_a_id / source_b_id), la
 * cardinalité attendue (1:1, 1:N, N:1, N:M), la priorité d'exécution et
 * le JSON criteria qui contient les tolérances (montant en millimes,
 * jours), les statuts exclus et la clé primaire de groupement. Les règles
 * actives sont exécutées séquentiellement par MatchingRuleController::runAll().
 *
 * Exemple de `criteria` (règle ALPHA ↔ BNA, voir MatchingRuleSeeder) :
 *   {
 *     "tolerance_amount_millimes": 0,          // écart de montant accepté
 *     "tolerance_days": 0,                     // écart de dates accepté
 *     "excluded_status_raw": {"a": [], "b": ["Commission"]}, // lignes BNA ignorées
 *     "primary_key": {"a": "num_autorisation", "b": "num_autorisation"}, // clé de regroupement
 *     "verify_fields": ["amount", "date"],     // champs contrôlés après regroupement
 *     "excluded_non_numeric": {...}            // clés non numériques écartées
 *   }
 *
 * (Les notions fillable, casts, relations et traits sont expliquées en
 * détail dans app/Models/Import.php.)
 */
use App\Enums\MatchingCardinality;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MatchingRule extends Model
{
    use Auditable, HasFactory, HasUserstamps, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'source_a_id',  // première source comparée (« côté A »)
        'source_b_id',  // seconde source comparée (« côté B »)
        'cardinality',
        'priority',     // les règles s'exécutent par priorité croissante (10 avant 20)
        'is_active',
        'criteria',     // paramètres de comparaison (voir l'exemple ci-dessus)
    ];

    protected function casts(): array
    {
        return [
            'cardinality' => MatchingCardinality::class,
            'priority' => 'integer',
            'is_active' => 'boolean',
            'criteria' => 'array',
        ];
    }

    /** La source du côté A (la colonne s'appelle source_a_id, d'où le 2e argument). */
    public function sourceA(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_a_id');
    }

    /** La source du côté B. */
    public function sourceB(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_b_id');
    }

    /** Relation plusieurs-à-plusieurs via la table matching_rule_sources (voir Source::matchingRules()). */
    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(Source::class, 'matching_rule_sources');
    }

    /** Tous les résultats produits par cette règle. */
    public function matchingResults(): HasMany
    {
        return $this->hasMany(MatchingResult::class);
    }
}
