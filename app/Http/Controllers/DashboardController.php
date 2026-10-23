<?php

namespace App\Http\Controllers;

/**
 * Tableau de bord : chiffres clés (DashboardMetricsService) et tableau de bord
 * analytique filtrable (analytics(), relu en JSON par resources/js/dashboard.js).
 *
 * Droits : l'analyse du rapprochement exige matching-results.viewAny ; les
 * statistiques d'opérations ne portent que sur les opérations de
 * l'utilisateur, sauf pour un administrateur (OperationPolicy::viewAll),
 * qui peut aussi choisir un utilisateur.
 * (Les notions de contrôleur sont expliquées dans Admin/ImportController.php.)
 */
use App\Enums\MatchingStatus;
use App\Models\Operation;
use App\Models\User;
use App\Services\Analytics\OperationAnalytics;
use App\Services\Analytics\ReconciliationAnalytics;
use App\Services\DashboardMetricsService;
use App\Support\DateDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Laravel fournit automatiquement le service (injection de dépendances). */
    public function __construct(private DashboardMetricsService $metrics) {}

    public function index(Request $request): View
    {
        $canAnalyse = $request->user()->can('matching-results.viewAny');
        $canViewAll = Gate::allows('viewAll', Operation::class);

        // Chaque clé du tableau devient une variable dans la vue resources/views/dashboard.blade.php.
        return view('dashboard', [
            'importStats' => $this->metrics->importStats(),
            'matchingStats' => $this->metrics->matchingStats(),
            'exceptionStats' => $this->metrics->exceptionStats(),
            // Volume et évolution simples : seulement pour qui n'a pas l'analyse détaillée (qui les remplace).
            'volumeBySource' => $canAnalyse ? [] : $this->metrics->transactionVolumeBySource(),
            'dailyTrend' => $canAnalyse ? [] : $this->metrics->dailyTransactionTrend(),
            'totalTransactions' => $this->metrics->totalTransactions(),
            'canAnalyse' => $canAnalyse,
            'canViewAllOperations' => $canViewAll,
            'analyticsOptions' => $canAnalyse ? app(ReconciliationAnalytics::class)->options() : null,
            'operationTypes' => Operation::TYPES,
            'users' => $canViewAll ? User::query()->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    /** Données du tableau de bord analytique, selon les filtres choisis (JSON). */
    public function analytics(Request $request, ReconciliationAnalytics $reconciliation, OperationAnalytics $operations): JsonResponse
    {
        $dimensions = array_keys(ReconciliationAnalytics::DIMENSIONS);
        $filters = $request->validate([
            'annee' => ['nullable', 'integer', 'between:2000,2100'],
            'date_debut' => ['nullable', 'date_format:Y-m-d'],
            'date_fin' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_debut'],
            'sources' => ['nullable', 'array'],
            'sources.*' => ['string', Rule::exists('sources', 'code')],
            'statuts' => ['nullable', 'array'],
            'statuts.*' => [Rule::enum(MatchingStatus::class)],
            'types' => ['nullable', 'array'],
            'types.*' => [Rule::in(array_keys(Operation::TYPES))],
            'utilisateur' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'dim_lignes' => ['nullable', Rule::in($dimensions)],
            'dim_colonnes' => ['nullable', 'different:dim_lignes', Rule::in([...$dimensions, 'aucune'])], // « aucune » : une seule colonne
            'mesure' => ['nullable', Rule::in(array_keys(ReconciliationAnalytics::MEASURES))],
            'actualiser' => ['nullable', 'boolean'],
        ], [
            'date_fin.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'dim_colonnes.different' => 'Choisissez deux regroupements différents pour les lignes et les colonnes.',
        ], [
            'annee' => 'année', 'date_debut' => 'date de début', 'date_fin' => 'date de fin',
            'dim_lignes' => 'regroupement des lignes', 'dim_colonnes' => 'regroupement des colonnes', 'mesure' => 'mesure',
        ]);
        $canAnalyse = $request->user()->can('matching-results.viewAny');
        if ($canAnalyse && $request->boolean('actualiser')) {
            $reconciliation->cube(fresh: true); // bouton « Actualiser » : recalcul immédiat
        }

        // Sans le droit de tout voir, l'utilisateur ne voit que ses propres opérations (filtre « utilisateur » ignoré).
        $userId = Gate::allows('viewAll', Operation::class) ? ($filters['utilisateur'] ?? null) : $request->user()->id;

        return response()->json([
            'rapprochement' => $canAnalyse ? [
                'calcule_le' => DateDisplay::dateTime($reconciliation->cube()['calcule_le']),
                'indicateurs' => $reconciliation->kpis($filters),
                'evolution' => $reconciliation->timeline($filters),
                'par_source' => $reconciliation->pivot($filters, 'source', 'statut', 'lignes'),
                'croise' => $reconciliation->pivot($filters, $filters['dim_lignes'] ?? 'source',
                    ($filters['dim_colonnes'] ?? 'statut') === 'aucune' ? null : ($filters['dim_colonnes'] ?? 'statut'), $filters['mesure'] ?? 'lignes'),
            ] : null,
            'operations' => $operations->summary($filters, $userId),
        ]);
    }
}
