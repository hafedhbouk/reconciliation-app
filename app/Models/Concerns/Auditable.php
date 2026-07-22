<?php

namespace App\Models\Concerns;

use App\Observers\AuditObserver;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::observe(AuditObserver::class);
    }

    /**
     * Attributes never written to audit_logs (secrets or high-churn noise).
     */
    public function auditExcludedAttributes(): array
    {
        return ['password', 'remember_token'];
    }
}
