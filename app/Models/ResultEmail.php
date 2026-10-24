<?php

namespace App\Models;

use App\Support\DateDisplay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Envoi des résultats d'un rapprochement à un destinataire (voir
 * App\Services\ResultEmails). La ligne est la trace de l'envoi : statut,
 * pièce jointe, erreur éventuelle, date d'envoi.
 *
 * Une seule ligne par (rapprochement, adresse) : c'est ce qui empêche
 * d'envoyer deux fois les mêmes résultats à la même personne.
 * (Les notions fillable, casts et relations sont expliquées dans app/Models/Import.php.)
 */
class ResultEmail extends Model
{
    public const STATUSES = [
        'waiting' => 'Envoi à la fin du rapprochement',
        'queued' => 'En file d’attente',
        'sending' => 'Envoi en cours',
        'sent' => 'Envoyé',
        'failed' => 'Échec de l’envoi',
        'cancelled' => 'Annulé',
    ];

    protected $fillable = [
        'operation_id', 'recipient_id', 'email', 'requested_by', 'with_attachment', 'status',
        'attempts', 'attachment_name', 'note', 'error_message', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'with_attachment' => 'boolean',
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Couleur du badge Bootstrap. */
    public function statusClass(): string
    {
        return match ($this->status) {
            'sent' => 'success',
            'failed' => 'danger',
            'cancelled' => 'secondary',
            default => 'primary',
        };
    }

    public function sentAtLabel(): ?string
    {
        return DateDisplay::dateTime($this->sent_at);
    }
}
