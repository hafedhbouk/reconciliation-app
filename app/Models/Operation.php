<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

/**
 * Une opération longue suivie en temps réel : import, rapprochement,
 * comparaison de fichiers, export.
 *
 * Les jobs écrivent l'avancement via App\Services\OperationTracker ; les
 * écrans le relisent via OperationController. Toutes les valeurs affichées
 * viennent du travail réellement fait : si le total n'est pas connu, aucun
 * pourcentage n'est calculé (percent() renvoie null).
 *
 * Volontairement sans le trait Auditable : l'avancement change toutes les
 * secondes et remplirait le journal d'audit.
 * (Les notions fillable, casts et relations sont expliquées dans app/Models/Import.php.)
 */
class Operation extends Model
{
    public const TYPES = [
        'import' => 'Import',
        'matching' => 'Rapprochement',
        'comparison' => 'Comparaison de fichiers',
        'export' => 'Export',
        'search_export' => 'Export de recherche',
    ];

    public const STATUSES = [
        'pending' => 'En attente',
        'running' => 'En cours',
        'completed' => 'Terminé',
        'failed' => 'Échec',
    ];

    /** Sans nouvelle du job depuis ce délai, l'écran le signale (le worker est peut-être arrêté). */
    public const STALE_AFTER_MINUTES = 5;

    protected $fillable = [
        'uuid', 'type', 'label', 'user_id', 'subject_type', 'subject_id', 'status', 'stage',
        'indeterminate', 'processed', 'total', 'succeeded', 'failed', 'error_message',
        'summary', 'result_url', 'started_at', 'finished_at', 'heartbeat_at',
    ];

    protected function casts(): array
    {
        return [
            'indeterminate' => 'boolean',
            'processed' => 'integer',
            'total' => 'integer',
            'succeeded' => 'integer',
            'failed' => 'integer',
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'heartbeat_at' => 'datetime',
        ];
    }

    /** Les URL utilisent l'uuid (/admin/operations/{uuid}), pas l'id interne. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** L'objet suivi (Import, MatchingExport, UnmatchedSnapshot…), s'il existe. */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Opération en attente ou en cours la plus récente sur cet objet (import, comparaison, export),
     * si l'utilisateur connecté a le droit de la voir ; sinon null.
     */
    public static function activeFor(?Model $subject): ?self
    {
        if ($subject === null) {
            return null;
        }
        $operation = self::query()->whereMorphedTo('subject', $subject)
            ->whereIn('status', ['pending', 'running'])->latest('id')->first();

        return $operation !== null && auth()->user()?->can('view', $operation) ? $operation : null;
    }

    /** Opérations actives de l'utilisateur connecté, pour les types donnés (ex. exports). */
    public static function activeForCurrentUser(array $types): Collection
    {
        return self::query()->where('user_id', auth()->id())->whereIn('type', $types)
            ->whereIn('status', ['pending', 'running'])->latest('id')->get();
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'running'], true);
    }

    /**
     * Pourcentage réel (0 à 100), ou null quand il ne peut pas être calculé :
     * total inconnu, total nul ou étape non mesurable (ex. écriture du fichier).
     */
    public function percent(): ?int
    {
        if ($this->status === 'completed') {
            return 100;
        }
        if ($this->indeterminate || ! $this->total) {
            return null;
        }

        // floor : on n'affiche jamais 100 % tant que tout n'est pas traité.
        return (int) min(99, floor($this->processed * 100 / $this->total));
    }

    /** Vrai si le job en cours ne donne plus de nouvelles (worker arrêté, plantage). */
    public function isStale(): bool
    {
        return $this->status === 'running'
            && ($this->heartbeat_at ?? $this->started_at)?->lt(now()->subMinutes(self::STALE_AFTER_MINUTES)) === true;
    }

    /** Données envoyées au navigateur (JSON), déjà formatées pour l'affichage. */
    public function toProgressArray(): array
    {
        $end = $this->finished_at ?? ($this->isActive() ? now() : null);

        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'type_label' => self::TYPES[$this->type] ?? $this->type,
            'label' => $this->label,
            'status' => $this->status,
            'status_label' => self::STATUSES[$this->status] ?? $this->status,
            'active' => $this->isActive(),
            'stale' => $this->isStale(),
            'stage' => $this->stage,
            'processed' => $this->processed,
            'total' => $this->total,
            'succeeded' => $this->succeeded,
            'failed' => $this->failed,
            'percent' => $this->percent(),
            // Une règle globale avance clé par clé ; les autres opérations, ligne par ligne.
            'unit' => $this->subject_type === (new MatchingRule)->getMorphClass() ? 'clés' : 'lignes',
            'error_message' => $this->error_message,
            'summary' => $this->summary,
            'result_url' => $this->result_url,
            'user' => $this->user?->name,
            'created_at' => $this->created_at?->format('d/m/Y H:i:s'),
            'started_at' => $this->started_at?->format('d/m/Y H:i:s'),
            'finished_at' => $this->finished_at?->format('d/m/Y H:i:s'),
            'duration_seconds' => $this->started_at && $end ? (int) $this->started_at->diffInSeconds($end, true) : null,
            'heartbeat_at' => $this->heartbeat_at?->format('d/m/Y H:i:s'),
        ];
    }
}
