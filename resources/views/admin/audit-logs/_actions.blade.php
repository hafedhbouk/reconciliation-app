{{--
    Bouton « voir » d'une ligne du journal d'audit (inséré dans le tableau par AuditLogController::data).
--}}
<a href="{{ route('admin.audit-logs.show', $log) }}" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-eye"></i>
</a>
