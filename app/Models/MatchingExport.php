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
 *
 * Pourquoi « asynchrone » ? Générer un Excel de 150 000 lignes prend
 * plusieurs minutes. Le navigateur n'attend pas : la page affiche « en
 * cours », un worker fait le travail en arrière-plan, puis le fichier
 * devient téléchargeable sur la page « Exports » (statut completed).
 * (Les notions fillable, casts, relations et traits sont expliquées dans app/Models/Import.php.)
 */
class MatchingExport extends Model
{
    /** @use HasFactory<MatchingExportFactory> */
    use Auditable, HasFactory, HasUserstamps, SoftDeletes;

    protected $fillable = [
        'user_id',        // utilisateur qui a demandé l'export
        'format',         // 'xlsx', 'csv' ou 'pdf'
        'status',         // 'pending', 'processing', 'completed' ou 'failed'
        'file_path',
        'download_token', // jeton aléatoire présent dans le lien de téléchargement
        'filters',        // filtres choisis à l'écran, rejoués par le job
        'error_message',
        'completed_at',
        'type', // 'matching' or 'search' or 'unmatched'
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

    /** Petites méthodes utilitaires, utilisées dans les vues : @if ($export->isCompleted()) ... */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
