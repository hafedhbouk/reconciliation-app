<?php

namespace App\Notifications;

use App\Models\Import;
use Illuminate\Notifications\Notification;

class ImportProcessedNotification extends Notification
{
    public function __construct(private Import $import)
    {
    }

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
