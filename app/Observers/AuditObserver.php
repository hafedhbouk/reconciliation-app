<?php

namespace App\Observers;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function __construct(private AuditLogService $auditLogService)
    {
    }

    public function created(Model $model): void
    {
        $this->auditLogService->logModelEvent('created', $model, null, $this->auditableAttributes($model));
    }

    public function updated(Model $model): void
    {
        $changes = $this->auditableAttributes($model, $model->getChanges());

        if (empty($changes)) {
            return;
        }

        $original = array_intersect_key($model->getOriginal(), $changes);

        $this->auditLogService->logModelEvent('updated', $model, $original, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->auditLogService->logModelEvent('deleted', $model, $this->auditableAttributes($model), null);
    }

    public function restored(Model $model): void
    {
        $this->auditLogService->logModelEvent('restored', $model, null, $this->auditableAttributes($model));
    }

    private function auditableAttributes(Model $model, ?array $attributes = null): array
    {
        $attributes ??= $model->getAttributes();

        $excluded = method_exists($model, 'auditExcludedAttributes')
            ? $model->auditExcludedAttributes()
            : [];

        return array_diff_key($attributes, array_flip($excluded));
    }
}
