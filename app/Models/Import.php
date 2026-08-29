<?php

namespace App\Models;

/**
 * Modèle représentant un fichier importé par les utilisateurs.
 *
 * Un Import est le point d'entrée du pipeline : il stocke le fichier brut,
 * son hachage SHA-256 (pour détecter les doublons), les métadonnées de
 * traitement (statut, durée, compteurs de lignes) et l'utilisateur à l'origine
 * de l'import. Les relations vers ImportRow, Transaction et Source permettent
 * de naviguer depuis le fichier jusqu'aux transactions normalisées.
 */
use App\Enums\ImportStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Import extends Model
{
    use HasFactory, SoftDeletes, HasUserstamps, Auditable;

    protected $fillable = [
        'source_id',
        'bank_id',
        'original_filename',
        'stored_path',
        'file_hash',
        'mime_type',
        'size_bytes',
        'status',
        'total_rows',
        'processed_rows',
        'success_rows',
        'error_rows',
        'started_at',
        'finished_at',
        'job_dispatched_at',
        'error_summary',
        'meta',
        'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'job_dispatched_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function importedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
