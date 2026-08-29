<?php

namespace App\Models;

/**
 * Devise monétaire.
 *
 * Stocke la précision décimale (decimal_places) car l'application travaille
 * en millimes (unité de compte) pour éviter les erreurs d'arrondi liées
 * aux flottants. La conversion decimalString -> millimes utilise cette
 * précision dans DecimalStringToMillimesTransform.
 */
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Currency extends Model
{
    use HasFactory, SoftDeletes, HasUserstamps, Auditable;

    protected $fillable = [
        'iso_code',
        'name',
        'symbol',
        'decimal_places',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
