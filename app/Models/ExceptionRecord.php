<?php

namespace App\Models;

/**
 * Exception ou anomalie détectée durant le processus de rapprochement.
 *
 * Couvre tous les cas : doublons (Duplicate), dépassements de tolérance
 * (AmountMismatch, DateMismatch), transactions non rapprochées (Unmatched)
 * et erreurs de parsing. Le cycle de vie est géré par le statut (Open,
 * InReview, Resolved) et les champs assigned_to / resolved_by permettent
 * le workflow de revue manuelle.
 *
 * Pourquoi « ExceptionRecord » et pas « Exception » ? Parce que `Exception`
 * est déjà une classe de base de PHP (les erreurs). D'où aussi
 * `$table = 'exceptions'` : sans cette ligne, Laravel chercherait une table
 * « exception_records ».
 * (Les notions fillable, casts, relations et traits sont expliquées dans app/Models/Import.php.)
 */
use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExceptionRecord extends Model
{
    use Auditable, HasFactory, HasUserstamps, SoftDeletes;

    protected $table = 'exceptions';

    protected $fillable = [
        'normalized_transaction_id', // transaction concernée (cas d'une seule transaction)
        'matching_result_id',        // ou résultat de rapprochement concerné (cas d'un groupe)
        'batch_reference',
        'type',                      // voir App\Enums\ExceptionType
        'status',                    // voir App\Enums\ExceptionStatus
        'is_expected',               // l'écart est connu et normal (ex. frais bancaires)
        'assigned_to',               // utilisateur chargé de l'analyser
        'resolution_comment',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExceptionType::class,
            'status' => ExceptionStatus::class,
            'is_expected' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function normalizedTransaction(): BelongsTo
    {
        return $this->belongsTo(NormalizedTransaction::class);
    }

    public function matchingResult(): BelongsTo
    {
        return $this->belongsTo(MatchingResult::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** Pièces jointes (justificatifs). La clé étrangère s'appelle exception_id. */
    public function attachments(): HasMany
    {
        return $this->hasMany(ExceptionAttachment::class, 'exception_id');
    }
}
