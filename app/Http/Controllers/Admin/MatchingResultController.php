<?php

namespace App\Http\Controllers\Admin;

use App\Exports\GenericTableExport;
use App\Http\Controllers\Controller;
use App\Models\MatchingResult;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Yajra\DataTables\Facades\DataTables;

class MatchingResultController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(MatchingResult::class, 'matching_result');
    }

    public function index(): View
    {
        return view('admin.matching-results.index');
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', MatchingResult::class);

        $results = MatchingResult::query()->with(['matchingRule', 'matchedByUser'])->select('matching_results.*');

        return DataTables::of($results)
            ->addColumn('rule_name', fn (MatchingResult $result) => $result->matchingRule?->name ?? __('Rapprochement manuel'))
            ->addColumn('status', fn (MatchingResult $result) => sprintf(
                '<span class="badge %s">%s</span>',
                $result->status->badgeClass(),
                $result->status->label()
            ))
            ->addColumn('matched_by', fn (MatchingResult $result) => $result->matchedByUser?->name ?? __('Automatique'))
            ->addColumn('actions', fn (MatchingResult $result) => '<a href="'.route('admin.matching-results.show', $result).'" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>')
            ->rawColumns(['status', 'actions'])
            ->toJson();
    }

    public function show(MatchingResult $matchingResult): View
    {
        $matchingResult->load([
            'matchingRule.sourceA',
            'matchingRule.sourceB',
            'matchedByUser',
            'matchingDetails.normalizedTransaction.transaction.source',
            'exceptions',
        ]);

        return view('admin.matching-results.show', ['result' => $matchingResult]);
    }

    public function export(string $format): BinaryFileResponse
    {
        $this->authorize('viewAny', MatchingResult::class);
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        $query = MatchingResult::query()->with(['matchingRule', 'matchedByUser'])->orderByDesc('id');

        // See SearchController::export() -- XLSX/PDF both build a full
        // in-memory object model and exhausted PHP's memory limit on a real
        // ~150k-row export (this exact resource); only CSV streams and stays
        // uncapped.
        if (in_array($format, ['xlsx', 'pdf'], true)) {
            $query->limit(1000);
        }

        $export = new GenericTableExport(
            $query,
            [__('Règle'), __('Statut'), __('Confiance'), __('Traité par'), __('Date')],
            fn (MatchingResult $result) => [
                $result->matchingRule?->name ?? __('Rapprochement manuel'),
                $result->status->label(),
                $result->confidence_score,
                $result->matchedByUser?->name ?? __('Automatique'),
                $result->matched_at?->format('d/m/Y H:i'),
            ],
        );

        $writerType = match ($format) {
            'csv' => ExcelFormat::CSV,
            'xlsx' => ExcelFormat::XLSX,
            'pdf' => ExcelFormat::DOMPDF,
        };

        return Excel::download($export, "matching-results.{$format}", $writerType);
    }
}
