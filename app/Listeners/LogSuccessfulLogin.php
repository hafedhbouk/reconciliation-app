<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function __construct(private AuditLogService $auditLogService)
    {
    }

    public function handle(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $this->auditLogService->logEvent('login');
    }
}
