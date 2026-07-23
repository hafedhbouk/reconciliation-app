<?php

namespace App\Http\Controllers\Admin;

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
        ]);

        $rows = NormalizedTransaction::query()
            ->where('matching_status', MatchingStatus::Unmatched->value)
            ->whereHas('transaction', fn ($query) => $query
                ->when($validated['source_id'] ?? null, fn ($q, $sourceId) => $q->where('source_id', $sourceId)))
            ->when($validated['reference'] ?? null, fn ($query, $reference) => $query->where('normalized_reference', 'like', "%{$reference}%"))
            ->when($validated['amount_min'] ?? null, fn ($query, $min) => $query->where('normalized_amount_millimes', '>=', $min))
            ->when($validated['amount_max'] ?? null, fn ($query, $max) => $query->where('normalized_amount_millimes', '<=', $max))
            ->when($validated['date_from'] ?? null, fn ($query, $date) => $query->where('normalized_date', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($query, $date) => $query->where('normalized_date', '<=', $date))
            ->with('transaction.source')
            ->orderByDesc('id')
            ->paginate(15);

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

            NormalizedTransaction::query()
                ->whereIn('id', [...$idsA, ...$idsB])
                ->update(['matching_status' => MatchingStatus::Matched->value]);

            return $matchingResult;
        });

        return redirect()->route('admin.matching-results.show', $result)->with('status', __('Rapprochement manuel créé avec succès.'));
    }
}
