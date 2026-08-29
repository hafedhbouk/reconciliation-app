<?php

namespace App\Notifications;

use App\Models\MatchingExport;
use Illuminate\Notifications\Notification;

class MatchingExportReadyNotification extends Notification
{
    /**
     * @param MatchingExport $export
     * @param string $downloadUrl URL de téléchargement sécurisé via token
     * @param string|null $errorMessage null si succès, message d'erreur sinon
     */
    public function __construct(
        private MatchingExport $export,
        private string $downloadUrl,
        private ?string $errorMessage = null,
    ) {
    }

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
            'completed_at' => $this->export->completed_at?->format('d/m/Y H:i'),
        ];
    }
}
