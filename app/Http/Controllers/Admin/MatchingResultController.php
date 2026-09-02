<?php

namespace App\Http\Controllers\Admin;

use App\Exports\GenericTableExport;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateMatchingExportJob;
use App\Models\MatchingExport;
use App\Models\MatchingResult;
use App\Models\MatchingRule;
use App\Models\NormalizedTransaction;
use App\Enums\MatchingStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Yajra\DataTables\Facades\DataTables;

/**
 * Contrôleur des résultats de rapprochement.
 *
 * Fonctionnalités :
 * - Liste des résultats avec filtres par règle, lot, date et statut
 * - Affichage détaillé d'un résultat (côtés A et B, exceptions liées)
 * - Suppression d'un résultat (avec suppression en cascade des détails et exceptions)
 * - Export complet aux formats CSV, Excel et PDF sans limite de lignes
 */
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
            ->addColumn('actions', fn (MatchingResult $result) => view('admin.matching-results._actions', ['result' => $result])->render())
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

        $sourceAId = $matchingResult->matchingRule->source_a_id;
        $sourceBId = $matchingResult->matchingRule->source_b_id;

        $matchedIds = $matchingResult->matchingDetails
            ->pluck('normalized_transaction_id')
            ->filter()
            ->unique()
            ->values();

        $unmatchedA = NormalizedTransaction::query()
            ->join('transactions', 'transactions.id', '=', 'normalized_transactions.transaction_id')
            ->where('transactions.source_id', $sourceAId)
            ->where('normalized_transactions.matching_status', MatchingStatus::Unmatched->value)
            ->when($matchedIds->isNotEmpty(), fn ($q) => $q->whereNotIn('normalized_transactions.id', $matchedIds))
            ->select('normalized_transactions.*', 'transactions.raw_payload')
            ->get();

        $unmatchedB = NormalizedTransaction::query()
            ->join('transactions', 'transactions.id', '=', 'normalized_transactions.transaction_id')
            ->where('transactions.source_id', $sourceBId)
            ->where('normalized_transactions.matching_status', MatchingStatus::Unmatched->value)
            ->when($matchedIds->isNotEmpty(), fn ($q) => $q->whereNotIn('normalized_transactions.id', $matchedIds))
            ->select('normalized_transactions.*', 'transactions.raw_payload')
            ->get();

        return view('admin.matching-results.show', [
            'result' => $matchingResult,
            'unmatchedA' => $unmatchedA,
            'unmatchedB' => $unmatchedB,
        ]);
    }

    /**
     * Supprime un résultat de rapprochement ainsi que ses détails et exceptions liées.
     */
    public function destroy(MatchingResult $matchingResult): RedirectResponse
    {
        $this->authorize('delete', $matchingResult);

        $matchingRuleName = $matchingResult->matchingRule?->name ?? __('Rapprochement manuel');

        // Supprime les détails et exceptions associés avant le résultat principal
        $matchingResult->matchingDetails()->delete();
        $matchingResult->exceptions()->delete();
        $matchingResult->delete();

        return redirect()->route('admin.matching-results.index')->with('status', __('Résultat de rapprochement supprimé avec succès (:rule).', ['rule' => $matchingRuleName]));
    }

    /**
     * Export synchrone limité à 1000 lignes pour les petits volumes.
     */
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
            ->orderByDesc('id')
            ->limit(1000);

        return $this->buildExport($query, $format);
    }

    /**
     * Lance un export asynchrone pour les gros volumes.
     * Crée un enregistrement MatchingExport et dispatche le job de génération.
     */
    public function exportAsync(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', MatchingResult::class);
        $request->validate([
            'format' => 'required|in:csv,xlsx,pdf',
            'matching_rule_id' => 'nullable|exists:matching_rules,id',
            'batch_reference' => 'nullable|string|max:255',
            'matched_at_from' => 'nullable|date',
            'matched_at_to' => 'nullable|date',
            'status' => 'nullable|in:matched,partial,conflict,rejected',
        ]);

        $filters = $request->only([
            'matching_rule_id',
            'batch_reference',
            'matched_at_from',
            'matched_at_to',
            'status',
        ]);

        $matchingExport = MatchingExport::query()->create([
            'user_id' => auth()->id(),
            'format' => $request->input('format'),
            'status' => 'pending',
            'filters' => $filters,
            'download_token' => Str::random(64),
        ]);

        GenerateMatchingExportJob::dispatch($matchingExport);

        return redirect()->route('admin.matching-results.exports')->with('status', __('Export lancé en arrière-plan. Vous serez notifié une fois prêt.'));
    }

    /**
     * Liste des exports de l'utilisateur connecté.
     */
    public function exports(Request $request): View
    {
        $this->authorize('viewAny', MatchingExport::class);

        $exports = MatchingExport::query()
            ->when($request->user()->cannot('viewAny', MatchingExport::class), fn ($q) => $q->where('user_id', $request->user()->id))
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.matching-results.exports', compact('exports'));
    }

    /**
     * Téléchargement sécurisé par token (pas d'auth requise).
     */
    public function downloadExport(string $token): BinaryFileResponse
    {
        $export = MatchingExport::query()->where('download_token', $token)->firstOrFail();

        if (! $export->isCompleted() || ! $export->file_path) {
            abort(404);
        }

        $path = Storage::path($export->file_path);

        if (! file_exists($path)) {
            abort(404);
        }

        return response()->download($path, "matching-results.{$export->format}");
    }

    /**
     * Construit l'export synchrone pour un query donné.
     */
    private function buildExport(\Illuminate\Database\Eloquent\Builder $query, string $format): BinaryFileResponse
    {
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
