<?php

namespace App\Listeners;

use App\Services\AuditLogService;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function __construct(private AuditLogService $auditLogService)
    {
    }

    public function handle(Failed $event): void
    {
        $this->auditLogService->logEvent('login_failed', [
            'email' => $event->credentials['email'] ?? null,
        ]);
    }
}
