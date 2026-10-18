<?php

namespace App\Models;

/**
 * Institution bancaire.
 *
 * Référence statique utilisée pour qualifier les Sources et les Imports.
 * Le code (ex: ALPHA, BNA) est l'identifiant métier ; le swift_code est
 * conservé pour traçabilité mais n'est pas exploité par le moteur de
 * rapprochement.
 * (Les notions fillable, casts, relations et traits sont expliquées dans app/Models/Import.php.)
 */
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends Model
{
    use Auditable, HasFactory, HasUserstamps, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'swift_code', // code international de la banque (BIC)
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** Les sources (fichiers) émises par cette banque. */
    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }
}
