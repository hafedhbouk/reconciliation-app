<?php

namespace App\Http\Controllers\Admin;

/**
 * Contrôleur de rapprochement manuel.
 *
 * Affiche les transactions non rapprochées et permet de créer des
 * rapprochements manuels (matching sans règle, avec confiance humaine).
 * L'action "Sélectionner tout" est plafonnée à 500 lignes pour éviter
 * des requêtes trop lourdes côté client et serveur.
 */
use App\Enums\MatchingResultStatus;
use App\Enums\MatchingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreManualMatchRequest;
use App\Models\MatchingDetail;
use App\Models\MatchingResult;
use App\Models\NormalizedTransaction;
use App\Models\Source;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReconciliationController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', MatchingResult::class);

        return view('admin.reconciliation.index', [
            'sources' => Source::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function unmatched(Request $request): View
    {
        $this->authorize('viewAny', MatchingResult::class);

        $validated = $request->validate([
            'import_a_id' => ['nullable', 'exists:imports,id'],
            'import_b_id' => ['nullable', 'exists:imports,id'],
        ]);

        $importAId = $validated['import_a_id'] ?? null;
        $importBId = $validated['import_b_id'] ?? null;

        $sources = Source::query()->where('is_active', true)->orderBy('name')->get();

        $snapshot = null;
        $unmatchedA = collect();
        $unmatchedB = collect();

        if ($importAId !== null && $importBId !== null && $importAId !== $importBId) {
            $snapshot = \App\Models\UnmatchedSnapshot::query()
                ->where('import_a_id', $importAId)
                ->where('import_b_id', $importBId)
                ->orderByDesc('id')
                ->first();

            if ($snapshot === null) {
                $snapshot = \App\Models\UnmatchedSnapshot::query()->create([
                    'import_a_id' => $importAId,
                    'import_b_id' => $importBId,
                    'status' => 'pending',
                ]);

                \App\Jobs\ComputeUnmatchedJob::dispatch($snapshot->id, auth()->id());
            }

            if ($snapshot->status === 'completed') {
                $unmatchedA = collect($snapshot->result_a ?? []);
                $unmatchedB = collect($snapshot->result_b ?? []);
            }
        }

        return view('admin.reconciliation.unmatched', [
            'sources' => $sources,
            'importAId' => $importAId,
            'importBId' => $importBId,
            'snapshot' => $snapshot,
            'unmatchedA' => $unmatchedA,
            'unmatchedB' => $unmatchedB,
        ]);
    }

    public function refreshUnmatched(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', MatchingResult::class);

        $validated = $request->validate([
            'import_a_id' => ['required', 'exists:imports,id'],
            'import_b_id' => ['required', 'exists:imports,id', 'different:import_a_id'],
        ]);

        $snapshot = \App\Models\UnmatchedSnapshot::query()
            ->where('import_a_id', $validated['import_a_id'])
            ->where('import_b_id', $validated['import_b_id'])
            ->orderByDesc('id')
            ->first();

        if ($snapshot) {
            $snapshot->update([
                'status' => 'pending',
                'result_a' => null,
                'result_b' => null,
                'error' => null,
                'started_at' => null,
                'completed_at' => null,
            ]);

            \App\Jobs\ComputeUnmatchedJob::dispatch($snapshot->id, auth()->id());
        }

        return redirect()->route('admin.reconciliation.unmatched', [
            'import_a_id' => $validated['import_a_id'],
            'import_b_id' => $validated['import_b_id'],
        ]);
    }

    /** Hard cap on how many rows a single "select all" can pull in at once. */
    private const SELECT_ALL_LIMIT = 500;

    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MatchingResult::class);

        $validated = $request->validate([
            'source_id' => ['nullable', 'exists:sources,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'amount_min' => ['nullable', 'integer'],
            'amount_max' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'all' => ['nullable', 'boolean'],
        ]);

        $query = $this->filteredUnmatchedQuery($validated);

        if ($validated['all'] ?? false) {
            // Récupérer SELECT_ALL_LIMIT + 1 pour détecter la troncature
            // sans charger toutes les lignes en mémoire.
            $ids = (clone $query)->orderByDesc('id')->limit(self::SELECT_ALL_LIMIT + 1)->pluck('id');

            return response()->json([
                'ids' => $ids->take(self::SELECT_ALL_LIMIT)->values(),
                'truncated' => $ids->count() > self::SELECT_ALL_LIMIT,
            ]);
        }

        $rows = $query->with('transaction.source')->orderByDesc('id')->paginate(15);

        $rows->getCollection()->transform(fn (NormalizedTransaction $nt) => [
            'id' => $nt->id,
            'source' => $nt->transaction?->source?->code,
            'reference' => $nt->normalized_reference,
            'amount_millimes' => $nt->normalized_amount_millimes,
            'date' => $nt->normalized_date?->format('d/m/Y'),
            'canal' => $nt->transaction?->canal,
        ]);

        return response()->json($rows);
    }

    /** @param array<string,mixed> $filters */
    private function filteredUnmatchedQuery(array $filters)
    {
        return NormalizedTransaction::query()
            ->where('matching_status', MatchingStatus::Unmatched->value)
            ->whereHas('transaction', fn ($query) => $query
                ->when($filters['source_id'] ?? null, fn ($q, $sourceId) => $q->where('source_id', $sourceId)))
            ->when($filters['reference'] ?? null, fn ($query, $reference) => $query->where('normalized_reference', 'like', "%{$reference}%"))
            ->when($filters['amount_min'] ?? null, fn ($query, $min) => $query->where('normalized_amount_millimes', '>=', $min))
            ->when($filters['amount_max'] ?? null, fn ($query, $max) => $query->where('normalized_amount_millimes', '<=', $max))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->where('normalized_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->where('normalized_date', '<=', $date));
    }

    public function store(StoreManualMatchRequest $request): RedirectResponse
    {
        $this->authorize('create', MatchingResult::class);

        $idsA = $request->input('normalized_transaction_ids_a');
        $idsB = $request->input('normalized_transaction_ids_b');

        // Human-triggered, low-volume action -- uses Eloquent (not bulk
        // insert like the automated matching jobs), so HasUserstamps and
        // Auditable fire normally here, unlike RuleMatcher's bulk writes.
        $result = DB::transaction(function () use ($idsA, $idsB, $request) {
            $matchingResult = MatchingResult::query()->create([
                'matching_rule_id' => null,
                'batch_reference' => null,
                'status' => MatchingResultStatus::Matched,
                'confidence_score' => null,
                'matched_by' => $request->user()->id,
                'matched_at' => now(),
            ]);

            foreach ([...$idsA, ...$idsB] as $id) {
                MatchingDetail::query()->create([
                    'matching_result_id' => $matchingResult->id,
                    'normalized_transaction_id' => $id,
                    'side' => in_array($id, $idsA) ? 'a' : 'b',
                ]);
            }

            foreach (collect([...$idsA, ...$idsB])->chunk(1000) as $chunk) {
                NormalizedTransaction::query()
                    ->whereIn('id', $chunk)
                    ->update(['matching_status' => MatchingStatus::Matched->value]);
            }

            return $matchingResult;
        });

        return redirect()->route('admin.matching-results.show', $result)->with('status', __('Rapprochement manuel créé avec succès.'));
    }
}
