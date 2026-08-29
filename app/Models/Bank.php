<?php

namespace App\Models;

/**
 * Institution bancaire.
 *
 * Référence statique utilisée pour qualifier les Sources et les Imports.
 * Le code (ex: ALPHA, BNA) est l'identifiant métier ; le swift_code est
 * conservé pour traçabilité mais n'est pas exploité par le moteur de
 * rapprochement.
 */
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends Model
{
    use HasFactory, SoftDeletes, HasUserstamps, Auditable;

    protected $fillable = [
        'code',
        'name',
        'swift_code',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }
}
