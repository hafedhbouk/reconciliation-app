<?php

namespace App\Models;

/**
 * Snapshot normalisé d'une transaction, optimisé pour le rapprochement.
 *
 * Contrairement à Transaction (qui conserve les données brutes et le payload
 * complet), NormalizedTransaction ne garde que les champs nécessaires au
 * matching : référence principale et secondaire, montant en millimes, date
 * et hash de dédoublonnage (dedup_hash). Le champ matching_status reflète l'état du
 * rapprochement (unmatched, matched, conflict) et est mis à jour par les
 * jobs de matching.
 *
 * Contenu des références selon la source (calculé par TransactionNormalizer) :
 * | Source | normalized_reference | normalized_secondary_reference |
 * |--------|----------------------|--------------------------------|
 * | ALPHA  | REFERENCE            | NUM_AUTO (N° autorisation)     |
 * | WEB    | reference            | recu_paie                      |
 * | BNA    | N° autorisation      | vide (null)                    |
 * | SMT    | "date|montant"       | vide (null)                    |
 *
 * (Les notions fillable, casts, relations et traits sont expliquées en
 * détail dans app/Models/Import.php.)
 */
use App\Enums\MatchingStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NormalizedTransaction extends Model
{
    use Auditable, HasFactory, HasUserstamps, SoftDeletes;

    protected $fillable = [
        'transaction_id',
        'normalized_reference',
        'normalized_secondary_reference',
        'normalized_amount_millimes',
        'normalized_date',
        'dedup_hash',       // empreinte unique servant à repérer les doublons
        'matching_status',  // voir App\Enums\MatchingStatus
    ];

    protected function casts(): array
    {
        return [
            'normalized_amount_millimes' => 'integer',
            'normalized_date' => 'date:Y-m-d',
            'matching_status' => MatchingStatus::class,
        ];
    }

    /**
     * « Scope » de requête : un filtre réutilisable.
     *
     * Une méthode nommée scopeXxx s'utilise ainsi :
     *   NormalizedTransaction::query()->fromActiveImports()->get();
     * Elle ne garde que les transactions dont l'import n'a pas été supprimé :
     * les transactions sans import (import_id null) ou dont l'import existe
     * encore (orWhereHas('import') ignore les imports soft-deleted).
     */
    public function scopeFromActiveImports(Builder $query): void
    {
        $query->whereHas('transaction', fn ($transaction) => $transaction->where(
            fn ($parent) => $parent->whereNull('import_id')->orWhereHas('import'),
        ));
    }

    /** La transaction complète d'origine. */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** Les lignes qui relient cette transaction à des résultats de rapprochement. */
    public function matchingDetails(): HasMany
    {
        return $this->hasMany(MatchingDetail::class);
    }

    /** Les exceptions (anomalies) qui concernent cette transaction. */
    public function exceptions(): HasMany
    {
        return $this->hasMany(ExceptionRecord::class);
    }
}
