<?php

namespace App\Http\Controllers\Admin;

use App\Exports\GenericTableExport;
use App\Http\Controllers\Controller;
use App\Models\MatchingResult;
use App\Models\MatchingRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $batches = MatchingResult::query()
            ->select('batch_reference', 'matched_at')
            ->distinct()
            ->orderByDesc('matched_at')
            ->get();

        $rules = MatchingRule::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.matching-results.index', compact('batches', 'rules'));
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MatchingResult::class);

        $results = MatchingResult::query()->with(['matchingRule', 'matchedByUser'])->select('matching_results.*');

        if ($request->filled('batch_reference')) {
            $results->where('batch_reference', $request->input('batch_reference'));
        }

        if ($request->filled('matching_rule_id')) {
            $results->where('matching_rule_id', $request->input('matching_rule_id'));
        }

        if ($request->filled('matched_at_from')) {
            $results->where('matched_at', '>=', $request->input('matched_at_from').' 00:00:00');
        }

        if ($request->filled('matched_at_to')) {
            $results->where('matched_at', '<=', $request->input('matched_at_to').' 23:59:59');
        }

        if ($request->filled('status')) {
            $results->where('status', $request->input('status'));
        }

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

        $query = MatchingResult::query()
            ->with([
                'matchingRule',
                'matchedByUser',
                'matchingDetails.normalizedTransaction.transaction.source',
            ])
            ->orderByDesc('id');

        // See SearchController::export() -- XLSX/PDF both build a full
        // in-memory object model and exhausted PHP's memory limit on a real
        // ~150k-row export (this exact resource); only CSV streams and stays
        // uncapped.
        if (in_array($format, ['xlsx', 'pdf'], true)) {
            $query->limit(1000);
        }

        // Side A/B transaction detail columns are included on every row (not
        // only conflicts) since the same export is the one linked from this
        // index for every status -- conflicts are the case that most needs
        // this detail to investigate the mismatch, but matched/partial rows
        // benefit from it too and filtering by status is already available
        // upstream in the results list.
        $sideColumns = fn (MatchingResult $result, string $side) => $result->matchingDetails
            ->where('side', $side)
            ->map(fn ($detail) => $detail->normalizedTransaction)
            ->filter();

        $export = new GenericTableExport(
            $query,
            [
                __('Règle'), __('Statut'), __('Confiance'), __('Traité par'), __('Date'),
                __('Source A'), __('Référence A'), __('Montant A'), __('Date A'),
                __('Source B'), __('Référence B'), __('Montant B'), __('Date B'),
            ],
            function (MatchingResult $result) use ($sideColumns) {
                $sideA = $sideColumns($result, 'a');
                $sideB = $sideColumns($result, 'b');

                return [
                    $result->matchingRule?->name ?? __('Rapprochement manuel'),
                    $result->status->label(),
                    $result->confidence_score,
                    $result->matchedByUser?->name ?? __('Automatique'),
                    $result->matched_at?->format('d/m/Y H:i'),
                    $sideA->map(fn ($nt) => $nt->transaction?->source?->code)->unique()->implode('; '),
                    $sideA->map(fn ($nt) => $nt->normalized_reference)->implode('; '),
                    $sideA->map(fn ($nt) => $nt->normalized_amount_millimes)->implode('; '),
                    $sideA->map(fn ($nt) => $nt->normalized_date?->format('d/m/Y'))->implode('; '),
                    $sideB->map(fn ($nt) => $nt->transaction?->source?->code)->unique()->implode('; '),
                    $sideB->map(fn ($nt) => $nt->normalized_reference)->implode('; '),
                    $sideB->map(fn ($nt) => $nt->normalized_amount_millimes)->implode('; '),
                    $sideB->map(fn ($nt) => $nt->normalized_date?->format('d/m/Y'))->implode('; '),
                ];
            },
        );

        $writerType = match ($format) {
            'csv' => ExcelFormat::CSV,
            'xlsx' => ExcelFormat::XLSX,
            'pdf' => ExcelFormat::DOMPDF,
        };

        return Excel::download($export, "matching-results.{$format}", $writerType);
    }
}
