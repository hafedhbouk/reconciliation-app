<?php

namespace App\Models;

/**
 * Snapshot normalisé d'une transaction, optimisé pour le rapprochement.
 *
 * Contrairement à Transaction (qui conserve les données brutes et le payload
 * complet), NormalizedTransaction ne garde que les champs nécessaires au
 * matching : référence normalisée, montant en millimes, date et un hash de
 * dédoublonnage (dedup_hash). Le champ matching_status reflète l'état du
 * rapprochement (unmatched, matched, conflict) et est mis à jour par les
 * jobs de matching.
 */
use App\Enums\MatchingStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NormalizedTransaction extends Model
{
    use HasFactory, SoftDeletes, HasUserstamps, Auditable;

    protected $fillable = [
        'transaction_id',
        'normalized_reference',
        'normalized_amount_millimes',
        'normalized_date',
        'dedup_hash',
        'matching_status',
    ];

    protected function casts(): array
    {
        return [
            'normalized_amount_millimes' => 'integer',
            'normalized_date' => 'date:Y-m-d',
            'matching_status' => MatchingStatus::class,
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function matchingDetails(): HasMany
    {
        return $this->hasMany(MatchingDetail::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ExceptionRecord::class);
    }
}
