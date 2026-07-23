<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MatchingResult;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
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
}
