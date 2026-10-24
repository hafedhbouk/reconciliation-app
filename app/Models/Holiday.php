<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Jour férié : module retiré de l'interface (Phase 5, voir
 * docs/25-retrait-jours-feries.md). Il n'était utilisé par aucune autre
 * fonctionnalité (le moteur de rapprochement règle la tolérance de dates dans
 * les critères de chaque règle).
 *
 * Le modèle et la table holidays sont conservés : aucune donnée historique
 * n'est supprimée, et la table ne sera supprimée qu'après validation explicite.
 * (Les notions fillable, casts et traits sont expliquées dans app/Models/Import.php.)
 */
class Holiday extends Model
{
    use Auditable, HasUserstamps, SoftDeletes;

    protected $fillable = [
        'holiday_date',
        'name',
        'country_code',        // ex. 'TN'
        'is_recurring_yearly', // vrai pour une fête à date fixe chaque année (ex. 20 mars)
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date:Y-m-d',
            'is_recurring_yearly' => 'boolean',
        ];
    }
}
