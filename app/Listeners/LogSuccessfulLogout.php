<?php

namespace App\Listeners;

use App\Services\AuditLogService;
use Illuminate\Auth\Events\Logout;

class LogSuccessfulLogout
{
    public function __construct(private AuditLogService $auditLogService)
    {
    }

    public function handle(Logout $event): void
    {
        $this->auditLogService->logEvent('logout');
    }
}
