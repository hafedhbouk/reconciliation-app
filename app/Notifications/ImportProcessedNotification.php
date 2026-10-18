<?php

namespace App\Notifications;

/**
 * Notification de fin de traitement d'un import.
 *
 * Stockée en base (driver database) pour affichage dans le tableau de
 * bord des notifications. Contient les compteurs de lignes traitées,
 * réussies et en erreur.
 *
 * GUIDE DÉBUTANT (valable pour app/Notifications) : une notification Laravel
 * s'envoie avec $user->notify(new ImportProcessedNotification($import)).
 *   - via()        : les canaux d'envoi. Ici seulement 'database', c'est-à-dire
 *                    une ligne dans la table notifications (pas d'email) ;
 *   - toDatabase() : les données enregistrées, en JSON, et relues par la vue
 *                    de la cloche (resources/views/notifications).
 */
use App\Models\Import;
use Illuminate\Notifications\Notification;

class ImportProcessedNotification extends Notification
{
    public function __construct(private Import $import) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'import_id' => $this->import->id,
            'source_code' => $this->import->source?->code,
            'original_filename' => $this->import->original_filename,
            'status' => $this->import->status->value,
            'success_rows' => $this->import->success_rows,
            'error_rows' => $this->import->error_rows,
        ];
    }
}
