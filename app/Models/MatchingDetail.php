<?php

namespace App\Models;

/**
 * Détail d'un résultat de rapprochement : lien entre une MatchingResult et
 * une NormalizedTransaction.
 *
 * Chaque ligne indique de quel côté (a ou b) de la règle se trouve la
 * transaction rapprochée. Ce modèle permet de reconstituer les paires
 * exactes et est utilisé par GenericTableExport pour afficher les résultats.
 *
 * C'est une « table de liaison » : elle relie deux autres tables. Elle n'a
 * pas les traits Auditable/SoftDeletes, car on crée ces lignes en masse
 * (plusieurs centaines de milliers par rapprochement).
 * (Les notions fillable et relations sont expliquées dans app/Models/Import.php.)
 */
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchingDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'matching_result_id',
        'normalized_transaction_id',
        'side', // 'a' = transaction de la source A de la règle, 'b' = de la source B
    ];

    public function matchingResult(): BelongsTo
    {
        return $this->belongsTo(MatchingResult::class);
    }

    public function normalizedTransaction(): BelongsTo
    {
        return $this->belongsTo(NormalizedTransaction::class);
    }
}
