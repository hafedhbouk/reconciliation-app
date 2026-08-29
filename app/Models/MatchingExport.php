<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Suivi d'un export asynchrone de résultats de rapprochement.
 *
 * Le job GenerateMatchingExportJob remplit progressivement cette ligne :
 * pending -> processing -> completed | failed.
 * Une fois complété, file_path pointe vers le fichier généré et
 * download_token permet un téléchargement sécurisé sans auth.
 */
class MatchingExport extends Model
{
    /** @use HasFactory<MatchingExportFactory> */
    use HasFactory, SoftDeletes, HasUserstamps, Auditable;

    protected $fillable = [
        'user_id',
        'format',
        'status',
        'file_path',
        'download_token',
        'filters',
        'error_message',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
