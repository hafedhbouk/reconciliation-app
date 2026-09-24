<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class AuditLogController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(AuditLog::class, 'audit_log');
    }

    public function index(): View
    {
        return view('admin.audit-logs.index');
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        // Keep large JSON payloads out of the listing's sort and response.
        // They remain available on the individual audit entry page.
        $logs = AuditLog::query()->with('user:id,name')->select([
            'audit_logs.id', 'audit_logs.user_id', 'audit_logs.event',
            'audit_logs.auditable_type', 'audit_logs.auditable_id',
            'audit_logs.ip_address', 'audit_logs.created_at',
        ]);

        return DataTables::of($logs)
            ->addColumn('user', fn (AuditLog $log) => $log->user?->name ?? __('Système'))
            ->addColumn('date', fn (AuditLog $log) => $log->created_at->format('d/m/Y H:i:s'))
            ->addColumn('subject', fn (AuditLog $log) => $log->auditable_type
                ? class_basename($log->auditable_type).' #'.$log->auditable_id
                : '—')
            ->addColumn('actions', fn (AuditLog $log) => view('admin.audit-logs._actions', ['log' => $log])->render())
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function show(AuditLog $audit_log): View
    {
        return view('admin.audit-logs.show', ['log' => $audit_log]);
    }
}
