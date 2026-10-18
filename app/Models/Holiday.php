<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Jour férié (géré dans Administration > Jours fériés).
 *
 * Simple référentiel consultable et modifiable dans l'interface. Attention :
 * le moteur de rapprochement ne l'utilise pas aujourd'hui (la tolérance de
 * dates se règle dans les critères de chaque règle).
 * (Les notions fillable, casts et traits sont expliquées dans app/Models/Import.php.)
 */
class Holiday extends Model
{
    use Auditable, HasFactory, HasUserstamps, SoftDeletes;

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
