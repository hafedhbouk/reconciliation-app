<?php

namespace App\Models;

/**
 * Transaction brute issue d'un fichier importé.
 *
 * Stocke toutes les colonnes transformées (montant en millimes, dates,
 * référence, canal) ainsi que le payload JSON complet de la ligne source
 * (raw_payload) pour les besoins de debug et de matching avancé. Chaque
 * transaction appartient à un Import et une Source, et possède au plus un
 * NormalizedTransaction (relation 1:1) qui en est la version allégée.
 *
 * Pourquoi des « millimes » ? 1 dinar tunisien = 1000 millimes. Les montants
 * sont stockés en nombres entiers de millimes (253.000 TND -> 253000) plutôt
 * qu'en nombres à virgule. On évite ainsi les erreurs d'arrondi des calculs
 * à virgule (0.1 + 0.2 ne fait pas exactement 0.3 en informatique).
 *
 * (Les notions fillable, casts, relations et traits sont expliquées en
 * détail dans app/Models/Import.php.)
 */
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use Auditable, HasFactory, HasUserstamps, SoftDeletes;

    protected $fillable = [
        'import_id',
        'import_row_id',        // la ligne de fichier d'origine
        'source_id',
        'bank_id',
        'currency_id',
        'external_reference',   // référence fournie par le fichier (null pour BNA et SMT)
        'transaction_date',
        'transaction_datetime',
        'amount_millimes',
        'canal',
        'raw_payload',          // toutes les valeurs transformées, en JSON (num_autorisation, status_raw…)
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date:Y-m-d', // objet date, sérialisé au format 2026-05-15
            'transaction_datetime' => 'datetime',
            'amount_millimes' => 'integer',
            'raw_payload' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function importRow(): BelongsTo
    {
        return $this->belongsTo(ImportRow::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /** La version normalisée de cette transaction (utilisée par le rapprochement). */
    public function normalizedTransaction(): HasOne
    {
        return $this->hasOne(NormalizedTransaction::class);
    }
}
