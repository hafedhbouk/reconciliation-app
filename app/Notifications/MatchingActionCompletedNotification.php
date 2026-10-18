<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Reused for every background matching action (single rule run, "Lancer
 * tout" batch, duplicate detection, unmatched sweep) rather than one
 * notification class per trigger -- they're all the same shape: a title and
 * a short list of summary lines.
 *
 * (En français : une seule classe pour toutes les fins de traitement de
 * rapprochement, avec un titre et quelques lignes de résumé.
 * Le fonctionnement des notifications est expliqué dans ImportProcessedNotification.)
 */
class MatchingActionCompletedNotification extends Notification
{
    /** @param array<int,string> $summaryLines */
    public function __construct(private string $title, private array $summaryLines) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'lines' => $this->summaryLines,
        ];
    }
}
