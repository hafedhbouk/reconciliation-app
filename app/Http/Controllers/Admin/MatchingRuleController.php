<?php

namespace App\Http\Controllers\Admin;

/**
 * Contrôleur de gestion des règles de rapprochement.
 *
 * Offre le CRUD des règles ainsi que les actions de déclenchement :
 *  - Lancer une règle unique
 *  - Lancer toutes les règles actives (Bus::chain séquentiel par priorité)
 *  - Lancer la détection de doublons
 *  - Lancer le balayage des non-rapprochés
 *
 * Chaque lancement crée un « lot » (batchReference = identifiant UUID
 * aléatoire) : tous les résultats et exceptions produits portent ce lot, ce
 * qui permet de les retrouver ou de les filtrer ensuite.
 * (Les notions de contrôleur sont expliquées dans ImportController.php.)
 */
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMatchingRuleRequest;
use App\Http\Requests\Admin\UpdateMatchingRuleRequest;
use App\Jobs\DetectDuplicatesJob;
use App\Jobs\NotifyMatchingBatchCompleteJob;
use App\Jobs\RunAdHocMatchingJob;
use App\Jobs\RunMatchingRuleJob;
use App\Jobs\SweepUnmatchedJob;
use App\Models\Import;
use App\Models\MatchingRule;
use App\Models\Operation;
use App\Models\Source;
use App\Services\Matching\DuplicateDetector;
use App\Services\Matching\UnmatchedSweeper;
use App\Services\OperationTracker;
use App\Services\ResultEmails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        $emails = app(ResultEmails::class);

        return view('admin.matching-rules.index', [
            'sources' => Source::query()->where('is_active', true)->orderBy('name')->get(),
            // Options « Envoyer les résultats par e-mail » du formulaire de comparaison.
            'emailRecipients' => auth()->user()->can(ResultEmails::PERMISSION) ? $emails->authorizedRecipients() : collect(),
            'canAttachResults' => $emails->canAttach(auth()->user()),
        ]);
    }

    /**
     * Page « Maintenance » : détection des doublons et balayage des non-rapprochés.
     * Avec ?preview=1, on affiche d'abord combien d'exceptions SERAIENT créées,
     * sans rien créer (aperçu avant confirmation).
     */
    public function maintenance(Request $request, DuplicateDetector $detector, UnmatchedSweeper $sweeper): View
    {
        $this->authorize('update', MatchingRule::class);
        $filters = $request->boolean('preview') ? $this->validateMaintenanceFilters($request) : [];
        $duplicatePreview = null;
        $unmatchedPreview = null;

        if ($filters !== []) {
            $duplicatePreview = $detector->scan(
                sourceId: $filters['source_id'] ?? null,
                importId: $filters['import_id'] ?? null,
                dateFrom: $filters['date_from'] ?? null,
                dateTo: $filters['date_to'] ?? null,
                preview: true,
            );
            $unmatchedPreview = $sweeper->previewCount(
                $filters['source_id'] ?? null,
                $filters['import_id'] ?? null,
                $filters['date_from'] ?? null,
                $filters['date_to'] ?? null,
            );
        }

        return view('admin.matching-rules.maintenance', [
            'sources' => Source::query()->where('is_active', true)->orderBy('name')->get(),
            'imports' => Import::query()->with('source')->where('status', 'completed')->orderByDesc('created_at')->limit(250)->get(),
            'filters' => $filters,
            'duplicatePreview' => $duplicatePreview,
            'unmatchedPreview' => $unmatchedPreview,
        ]);
    }

    /** Données JSON du tableau des règles (voir ImportController::data pour DataTables). */
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
        $matchingRule->update($this->mapAttributes($request->validated(), $matchingRule->criteria ?? []));

        return redirect()->route('admin.matching-rules.index')->with('status', __('Règle de rapprochement mise à jour avec succès.'));
    }

    public function destroy(MatchingRule $matchingRule): RedirectResponse
    {
        $matchingRule->delete();

        return redirect()->route('admin.matching-rules.index')->with('status', __('Règle de rapprochement supprimée avec succès.'));
    }

    /**
     * Bouton « Lancer » d'une règle. On peut la limiter à deux fichiers
     * précis (import_a_id et import_b_id, l'un n'allant pas sans l'autre).
     */
    public function run(MatchingRule $matchingRule, Request $request): RedirectResponse
    {
        $this->authorize('update', $matchingRule);

        // Règles de validation : le fichier A doit exister, être terminé, et
        // appartenir à la source A de la règle (même chose pour B).
        $request->validate([
            'import_a_id' => ['nullable', 'required_with:import_b_id', 'integer', 'different:import_b_id',
                Rule::exists('imports', 'id')->where('source_id', $matchingRule->source_a_id)->where('status', 'completed')],
            'import_b_id' => ['nullable', 'required_with:import_a_id', 'integer', 'different:import_a_id',
                Rule::exists('imports', 'id')->where('source_id', $matchingRule->source_b_id)->where('status', 'completed')],
            ...$this->emailRules(),
        ], $this->emailMessages());

        $importIdA = $request->input('import_a_id') ? (int) $request->input('import_a_id') : null;
        $importIdB = $request->input('import_b_id') ? (int) $request->input('import_b_id') : null;

        $names = Import::query()->whereIn('id', array_filter([$importIdA, $importIdB]))->pluck('original_filename', 'id');
        $operation = app(OperationTracker::class)->create('matching', $importIdA !== null
            ? 'Rapprochement '.($names[$importIdA] ?? '?').' ↔ '.($names[$importIdB] ?? '?')
            : 'Règle « '.$matchingRule->name.' »', auth()->id(), $importIdA !== null ? null : $matchingRule);
        $emailNote = $this->requestEmails($request, $operation);
        RunMatchingRuleJob::dispatch($matchingRule->id, (string) Str::uuid(), auth()->id(), $importIdA, $importIdB, $operation->id);

        return redirect()->route('admin.matching-rules.index')->with('status', __('Règle « :name » lancée.', ['name' => $matchingRule->name]).$emailNote);
    }

    /** « Comparer deux fichiers » : deux imports terminés, de sources différentes. */
    public function runAdHoc(Request $request): RedirectResponse
    {
        $this->authorize('update', MatchingRule::class);

        $request->validate([
            'import_a_id' => ['required', 'integer', 'different:import_b_id', Rule::exists('imports', 'id')->where('status', 'completed')],
            'import_b_id' => ['required', 'integer', 'different:import_a_id', Rule::exists('imports', 'id')->where('status', 'completed')],
            ...$this->emailRules(),
        ], $this->emailMessages());

        $importA = Import::query()->findOrFail($request->input('import_a_id'));
        $importB = Import::query()->findOrFail($request->input('import_b_id'));

        if ($importA->source_id === $importB->source_id) {
            return redirect()->route('admin.matching-rules.index')->with('error', __('Veuillez sélectionner deux fichiers provenant de sources différentes.'));
        }

        $operation = app(OperationTracker::class)->create('matching',
            'Rapprochement '.$importA->original_filename.' ↔ '.$importB->original_filename, auth()->id());
        $emailNote = $this->requestEmails($request, $operation);
        RunAdHocMatchingJob::dispatch(
            $importA->id,
            $importB->id,
            (string) Str::uuid(),
            auth()->id(),
            $operation->id,
        );

        return redirect()->route('admin.matching-rules.index')->with('status', __('Rapprochement entre « :a » et « :b » lancé.', ['a' => $importA->original_filename, 'b' => $importB->original_filename]).$emailNote);
    }

    /** Bouton « Lancer tout » : toutes les règles actives, l'une après l'autre. */
    public function runAll(): RedirectResponse
    {
        $this->authorize('update', MatchingRule::class);

        $batchReference = (string) Str::uuid();

        // Construire la chaîne de jobs : chaque règle active (triée par
        // priorité), puis détection des doublons, puis balayage des
        // non-rapprochés, enfin notification agrégée.
        $ruleJobs = MatchingRule::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->get()
            // Une opération « En attente » par règle, visible dès le clic dans « Mes opérations ».
            ->map(fn (MatchingRule $rule) => new RunMatchingRuleJob($rule->id, $batchReference, null, null, null,
                app(OperationTracker::class)->create('matching', 'Règle « '.$rule->name.' »', auth()->id(), $rule)->id))
            ->all();

        $jobs = [
            ...$ruleJobs,
            new DetectDuplicatesJob(batchReference: $batchReference),
            new SweepUnmatchedJob(batchReference: $batchReference),
        ];

        if (auth()->id() !== null) {
            $jobs[] = new NotifyMatchingBatchCompleteJob($batchReference, auth()->id());
        }

        // Bus::chain : les jobs s'exécutent dans l'ordre, chacun après la fin du précédent.
        Bus::chain($jobs)->dispatch();

        return redirect()->route('admin.matching-rules.index')->with('status', __('Toutes les règles actives ont été lancées, par ordre de priorité.'));
    }

    /** Lance la détection des doublons (filtres facultatifs : source, fichier, période). */
    public function detectDuplicates(Request $request): RedirectResponse
    {
        $this->authorize('update', MatchingRule::class);
        $filters = $this->validateMaintenanceFilters($request);
        $batchReference = (string) Str::uuid();

        DetectDuplicatesJob::dispatch(
            $filters['source_id'] ?? null,
            auth()->id(),
            $filters['import_id'] ?? null,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null,
            $batchReference,
        );

        return redirect()->route('admin.matching-rules.index')->with('status', __('Détection des doublons lancée (lot :batch).', ['batch' => $batchReference]));
    }

    /** Lance le balayage des non-rapprochés (mêmes filtres que la détection des doublons). */
    public function sweepUnmatched(Request $request): RedirectResponse
    {
        $this->authorize('update', MatchingRule::class);
        $filters = $this->validateMaintenanceFilters($request);
        $batchReference = (string) Str::uuid();

        SweepUnmatchedJob::dispatch(
            $filters['source_id'] ?? null,
            auth()->id(),
            $filters['import_id'] ?? null,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null,
            $batchReference,
        );

        return redirect()->route('admin.matching-rules.index')->with('status', __('Balayage des non-rapprochés lancé (lot :batch).', ['batch' => $batchReference]));
    }

    /**
     * Valide les filtres de maintenance. Si une source ET un fichier sont choisis,
     * le fichier doit appartenir à cette source, sinon on renvoie une erreur de formulaire.
     */
    /** Options « Envoyer les résultats par e-mail à la fin » : destinataires autorisés seulement. */
    private function emailRules(): array
    {
        $allowed = auth()->user()->can(ResultEmails::PERMISSION);

        return [
            'email_envoyer' => $allowed ? ['nullable', 'boolean'] : ['nullable', 'declined'],
            'email_destinataires' => ['nullable', 'array', 'required_if_accepted:email_envoyer'],
            'email_destinataires.*' => ['integer', Rule::in(app(ResultEmails::class)->authorizedRecipients()->pluck('id')->all())],
            'email_piece_jointe' => ['nullable', 'boolean'],
        ];
    }

    private function emailMessages(): array
    {
        return [
            'email_envoyer.declined' => __('Vous n’avez pas le droit d’envoyer les résultats de rapprochement.'),
            'email_destinataires.required_if_accepted' => __('Choisissez au moins un destinataire pour l’envoi par e-mail.'),
            'email_destinataires.*.in' => __('Ce destinataire n’est pas autorisé à recevoir les résultats de rapprochement.'),
        ];
    }

    /** Enregistre l'envoi demandé au lancement : il partira à la fin du rapprochement. Renvoie la phrase à ajouter au message. */
    private function requestEmails(Request $request, Operation $operation): string
    {
        if (! $request->boolean('email_envoyer')) {
            return '';
        }
        $result = app(ResultEmails::class)->request($operation, $request->user(),
            (array) $request->input('email_destinataires', []), $request->boolean('email_piece_jointe'));

        return ' '.trans_choice('{1} Les résultats seront envoyés par e-mail à :count destinataire à la fin.|[2,*] Les résultats seront envoyés par e-mail à :count destinataires à la fin.',
            count($result['added']), ['count' => count($result['added'])]);
    }

    private function validateMaintenanceFilters(Request $request): array
    {
        $validated = $request->validate([
            'source_id' => ['nullable', 'integer', 'exists:sources,id'],
            'import_id' => ['nullable', 'integer', Rule::exists('imports', 'id')->where('status', 'completed')],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        if (! empty($validated['source_id']) && ! empty($validated['import_id'])) {
            $import = Import::query()->find($validated['import_id']);
            if ($import?->source_id !== (int) $validated['source_id']) {
                throw ValidationException::withMessages([
                    'import_id' => __('Le fichier sélectionné ne correspond pas à la source choisie.'),
                ]);
            }
        }

        return $validated;
    }

    /**
     * Builds the `criteria` JSON column from discrete form fields, mirroring
     * SourceMappingController's pattern of assembling a structured value
     * from a flat form payload.
     *
     * Le formulaire ne gère que les tolérances et les statuts exclus : les
     * autres critères de la règle (clé de rapprochement, champs vérifiés…)
     * sont conservés, sinon la règle retomberait sur la clé « référence ».
     *
     * @param  array<string,mixed>  $criteria  critères actuels de la règle (vide à la création)
     */
    private function mapAttributes(array $validated, array $criteria = []): array
    {
        // Le formulaire donne les statuts exclus en texte « Commission, Frais » :
        // explode découpe sur les virgules, trim retire les espaces, array_filter retire les vides.
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
            // array_replace : les valeurs du formulaire remplacent les anciennes, les autres clés restent.
            'criteria' => array_replace($criteria, [
                'tolerance_amount_millimes' => (int) $validated['tolerance_amount_millimes'],
                'tolerance_days' => (int) $validated['tolerance_days'],
                'excluded_status_raw' => ['a' => $excludedA, 'b' => $excludedB],
            ]),
        ];
    }
}
