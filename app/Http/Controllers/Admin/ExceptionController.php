<?php

namespace App\Http\Controllers\Admin;

/**
 * Contrôleur de gestion des exceptions de rapprochement.
 *
 * Permet de consulter, filtrer, assigner et résoudre les anomalies
 * (doublons, dépassements de tolérance, non-rapprochés). L'export est
 * limité pour XLSX/PDF pour les mêmes raisons de mémoire que
 * SearchController.
 */
use App\Enums\ExceptionStatus;
use App\Exports\GenericTableExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateExceptionRequest;
use App\Models\ExceptionRecord;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Yajra\DataTables\Facades\DataTables;

class ExceptionController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(ExceptionRecord::class, 'exception');
    }

    public function index(): View
    {
        return view('admin.exceptions.index');
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', ExceptionRecord::class);

        $exceptions = ExceptionRecord::query()
            ->with(['normalizedTransaction.transaction.source', 'assignedTo'])
            ->select('exceptions.*');

        return DataTables::of($exceptions)
            ->addColumn('type_label', fn (ExceptionRecord $exception) => $exception->type->label())
            ->addColumn('status', fn (ExceptionRecord $exception) => sprintf(
                '<span class="badge %s">%s</span>',
                $exception->status->badgeClass(),
                $exception->status->label()
            ))
            ->addColumn('source_reference', fn (ExceptionRecord $exception) => $this->sourceReferenceSnapshot($exception))
            ->addColumn('assigned_to', fn (ExceptionRecord $exception) => $exception->assignedTo?->name ?? '—')
            ->addColumn('actions', fn (ExceptionRecord $exception) => '<a href="'.route('admin.exceptions.show', $exception).'" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>')
            ->rawColumns(['status', 'actions'])
            ->toJson();
    }

    public function show(ExceptionRecord $exception): View
    {
        $exception->load([
            'normalizedTransaction.transaction.source',
            'matchingResult',
            'assignedTo',
            'resolvedBy',
            'attachments.uploadedBy',
        ]);

        return view('admin.exceptions.show', [
            'exception' => $exception,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateExceptionRequest $request, ExceptionRecord $exception): RedirectResponse
    {
        $data = $request->validated();

        if (($data['status'] ?? null) === ExceptionStatus::Resolved->value && $exception->status !== ExceptionStatus::Resolved) {
            $data['resolved_by'] = $request->user()->id;
            $data['resolved_at'] = now();
        }

        $exception->update($data);

        return redirect()->route('admin.exceptions.show', $exception)->with('status', __('Exception mise à jour avec succès.'));
    }

    public function export(string $format): BinaryFileResponse
    {
        $this->authorize('viewAny', ExceptionRecord::class);
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        $query = ExceptionRecord::query()->with(['normalizedTransaction.transaction.source', 'assignedTo'])->orderByDesc('id');

        // See SearchController::export() -- XLSX/PDF both build a full
        // in-memory object model and exhausted PHP's memory limit on a real
        // ~150k-row export; only CSV streams and stays uncapped.
        if (in_array($format, ['xlsx', 'pdf'], true)) {
            $query->limit(1000);
        }

        $export = new GenericTableExport(
            $query,
            [__('Type'), __('Statut'), __('Source / Référence'), __('Assigné à'), __('Créé le')],
            fn (ExceptionRecord $exception) => [
                $exception->type->label(),
                $exception->status->label(),
                $this->sourceReferenceSnapshot($exception),
                $exception->assignedTo?->name ?? '—',
                $exception->created_at?->format('d/m/Y H:i'),
            ],
        );

        $writerType = match ($format) {
            'csv' => ExcelFormat::CSV,
            'xlsx' => ExcelFormat::XLSX,
            'pdf' => ExcelFormat::DOMPDF,
        };

        return Excel::download($export, "exceptions.{$format}", $writerType);
    }

    private function sourceReferenceSnapshot(ExceptionRecord $exception): string
    {
        $nt = $exception->normalizedTransaction;

        if (! $nt) {
            return '—';
        }

        return ($nt->transaction?->source?->code ?? '?').' / '.$nt->normalized_reference;
    }
}
