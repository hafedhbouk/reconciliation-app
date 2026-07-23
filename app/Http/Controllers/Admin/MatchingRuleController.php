<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMatchingRuleRequest;
use App\Http\Requests\Admin\UpdateMatchingRuleRequest;
use App\Jobs\DetectDuplicatesJob;
use App\Jobs\RunMatchingRuleJob;
use App\Jobs\SweepUnmatchedJob;
use App\Models\MatchingRule;
use App\Models\Source;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class MatchingRuleController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(MatchingRule::class, 'matching_rule');
    }

    public function index(): View
    {
        return view('admin.matching-rules.index');
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', MatchingRule::class);

        $rules = MatchingRule::query()->with(['sourceA', 'sourceB'])->select('matching_rules.*');

        return DataTables::of($rules)
            ->addColumn('source_a', fn (MatchingRule $rule) => $rule->sourceA?->code)
            ->addColumn('source_b', fn (MatchingRule $rule) => $rule->sourceB?->code)
            ->addColumn('cardinality_label', fn (MatchingRule $rule) => $rule->cardinality->label())
            ->addColumn('is_active_label', fn (MatchingRule $rule) => $rule->is_active
                ? '<span class="badge bg-success">'.__('Actif').'</span>'
                : '<span class="badge bg-secondary">'.__('Inactif').'</span>')
            ->addColumn('actions', fn (MatchingRule $rule) => view('admin.matching-rules._actions', ['rule' => $rule])->render())
            ->rawColumns(['is_active_label', 'actions'])
            ->toJson();
    }

    public function create(): View
    {
        return view('admin.matching-rules.create', [
            'sources' => Source::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreMatchingRuleRequest $request): RedirectResponse
    {
        MatchingRule::query()->create($this->mapAttributes($request->validated()));

        return redirect()->route('admin.matching-rules.index')->with('status', __('Règle de rapprochement créée avec succès.'));
    }

    public function edit(MatchingRule $matchingRule): View
    {
        return view('admin.matching-rules.edit', [
            'rule' => $matchingRule,
            'sources' => Source::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateMatchingRuleRequest $request, MatchingRule $matchingRule): RedirectResponse
    {
        $matchingRule->update($this->mapAttributes($request->validated()));

        return redirect()->route('admin.matching-rules.index')->with('status', __('Règle de rapprochement mise à jour avec succès.'));
    }

    public function destroy(MatchingRule $matchingRule): RedirectResponse
    {
        $matchingRule->delete();

        return redirect()->route('admin.matching-rules.index')->with('status', __('Règle de rapprochement supprimée avec succès.'));
    }

    public function run(MatchingRule $matchingRule): RedirectResponse
    {
        $this->authorize('update', $matchingRule);

        RunMatchingRuleJob::dispatch($matchingRule->id, (string) Str::uuid());

        return redirect()->route('admin.matching-rules.index')->with('status', __('Règle « :name » lancée.', ['name' => $matchingRule->name]));
    }

    public function runAll(): RedirectResponse
    {
        $this->authorize('update', MatchingRule::class);

        $batchReference = (string) Str::uuid();

        $ruleJobs = MatchingRule::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->get()
            ->map(fn (MatchingRule $rule) => new RunMatchingRuleJob($rule->id, $batchReference))
            ->all();

        $jobs = [...$ruleJobs, new DetectDuplicatesJob(), new SweepUnmatchedJob()];

        Bus::chain($jobs)->dispatch();

        return redirect()->route('admin.matching-rules.index')->with('status', __('Toutes les règles actives ont été lancées, par ordre de priorité.'));
    }

    public function detectDuplicates(): RedirectResponse
    {
        $this->authorize('update', MatchingRule::class);

        DetectDuplicatesJob::dispatch();

        return redirect()->route('admin.matching-rules.index')->with('status', __('Détection des doublons lancée.'));
    }

    public function sweepUnmatched(): RedirectResponse
    {
        $this->authorize('update', MatchingRule::class);

        SweepUnmatchedJob::dispatch();

        return redirect()->route('admin.matching-rules.index')->with('status', __('Balayage des transactions non rapprochées lancé.'));
    }

    /**
     * Builds the `criteria` JSON column from discrete form fields, mirroring
     * SourceMappingController's pattern of assembling a structured value
     * from a flat form payload.
     */
    private function mapAttributes(array $validated): array
    {
        $excludedA = array_values(array_filter(array_map('trim', explode(',', $validated['excluded_status_raw_a'] ?? ''))));
        $excludedB = array_values(array_filter(array_map('trim', explode(',', $validated['excluded_status_raw_b'] ?? ''))));

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'source_a_id' => $validated['source_a_id'],
            'source_b_id' => $validated['source_b_id'],
            'cardinality' => $validated['cardinality'],
            'priority' => $validated['priority'],
            'is_active' => $validated['is_active'] ?? false,
            'criteria' => [
                'tolerance_amount_millimes' => (int) $validated['tolerance_amount_millimes'],
                'tolerance_days' => (int) $validated['tolerance_days'],
                'excluded_status_raw' => ['a' => $excludedA, 'b' => $excludedB],
            ],
        ];
    }
}
