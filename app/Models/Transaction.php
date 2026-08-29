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
    use HasFactory, SoftDeletes, HasUserstamps, Auditable;

    protected $fillable = [
        'import_id',
        'import_row_id',
        'source_id',
        'bank_id',
        'currency_id',
        'external_reference',
        'transaction_date',
        'transaction_datetime',
        'amount_millimes',
        'canal',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date:Y-m-d',
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

    public function normalizedTransaction(): HasOne
    {
        return $this->hasOne(NormalizedTransaction::class);
    }
}
