<?php

namespace App\Notifications;

/**
 * Notification de disponibilité d'un export de matching.
 *
 * Contient le statut de l'export (complété ou échoué), l'URL de
 * téléchargement sécurisée par token et, en cas d'erreur, le message
 * correspondant. Stockée en base pour le tableau de bord.
 *
 * Remarque : cette classe n'est envoyée nulle part pour l'instant. Les jobs
 * d'export mettent à jour matching_exports, et l'utilisateur consulte la page
 * « Exports » pour télécharger son fichier.
 * (Le fonctionnement des notifications est expliqué dans ImportProcessedNotification.)
 */
use App\Models\MatchingExport;
use App\Support\DateDisplay;
use Illuminate\Notifications\Notification;

class MatchingExportReadyNotification extends Notification
{
    /**
     * @param  string  $downloadUrl  URL de téléchargement sécurisé via token
     * @param  string|null  $errorMessage  null si succès, message d'erreur sinon
     */
    public function __construct(
        private MatchingExport $export,
        private string $downloadUrl,
        private ?string $errorMessage = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'matching_export_id' => $this->export->id,
            'format' => $this->export->format,
            'status' => $this->export->status,
            'download_url' => $this->downloadUrl,
            'error_message' => $this->errorMessage,
            'filters' => $this->export->filters,
            'completed_at' => DateDisplay::dateTime($this->export->completed_at),
        ];
    }
}
