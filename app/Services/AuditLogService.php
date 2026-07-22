<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public function logModelEvent(string $event, Model $model, ?array $oldValues, ?array $newValues): void
    {
        $this->log($event, $oldValues, $newValues, $model);
    }

    public function logEvent(string $event, array $context = []): void
    {
        $this->log($event, null, $context ?: null, null);
    }

    private function log(string $event, ?array $oldValues, ?array $newValues, ?Model $auditable): void
    {
        AuditLog::query()->create([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'url' => Request::fullUrl(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
